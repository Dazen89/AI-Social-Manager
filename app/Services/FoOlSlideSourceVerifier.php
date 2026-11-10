<?php

namespace App\Services;

use App\Exceptions\EditorialSourceVerificationException;
use App\Models\EditorialContent;
use App\Models\EditorialSource;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class FoOlSlideSourceVerifier
{
    private const MAX_RESPONSE_BYTES = 262144;

    private const MAX_IMAGE_BYTES = 5_000_000;

    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly PublicHostResolver $resolver,
        private readonly LocalFoOlSlideUrl $localUrl,
    ) {}

    public function verify(EditorialSource $source): bool
    {
        try {
            [$host, $address, $port] = $this->safeTarget($source);
            $result = $this->readJson($source->apiChaptersUrl().'?page=1&per_page=1', $host, $address, $port);

            if ($result['status'] === 200) {
                $chapters = $this->chapterEntries($result['json']['chapters'] ?? null, 1);

                if ($chapters === null) {
                    return $this->failed($source, 'L’API risponde, ma il formato dei capitoli non è quello previsto da FoOlSlide.');
                }

                return $this->verified($source, 'API FoOlSlide raggiungibile; elenco dei capitoli disponibile.');
            }

            // FoOlSlide returns 404 when the chapter list is empty. Its comic-count endpoint
            // still responds with a JSON count, allowing an empty installation to be verified.
            if ($result['status'] === 404) {
                $count = $this->readJson($source->base_url.'/api/reader/comics/count/comics/format/json', $host, $address, $port);

                if ($count['status'] === 200 && is_int($count['json']['count'] ?? null) && $count['json']['count'] >= 0) {
                    return $this->verified($source, 'API FoOlSlide raggiungibile; nessun capitolo nella prima pagina.');
                }

                return $this->failed($source, 'Endpoint FoOlSlide non trovato. Controlla l’URL base e la configurazione delle riscritture URL.');
            }

            return $this->failed($source, match (true) {
                in_array($result['status'], [301, 302, 303, 307, 308], true) => 'L’API reindirizza la richiesta. Inserisci l’URL finale dell’installazione FoOlSlide.',
                in_array($result['status'], [401, 403], true) => 'L’API non consente la lettura pubblica. Controlla i permessi della fonte.',
                $result['status'] === 429 => 'La fonte ha limitato le richieste. Attendi prima di riprovare.',
                $result['status'] >= 500 => 'La fonte ha restituito un errore del server. Riprova più tardi.',
                default => 'L’API FoOlSlide non ha restituito una risposta valida.',
            });
        } catch (EditorialSourceVerificationException $exception) {
            return $this->failed($source, $exception->getMessage());
        } catch (Throwable) {
            return $this->failed($source, 'Impossibile contattare l’API FoOlSlide. Controlla URL, rete e disponibilità del sito.');
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function chapterPage(EditorialSource $source, int $page, int $perPage = 20): array
    {
        if ($page < 1 || $page > 3 || $perPage < 1 || $perPage > 20) {
            throw new EditorialSourceVerificationException('La richiesta di importazione supera i limiti consentiti.');
        }

        [$host, $address, $port] = $this->safeTarget($source);
        $url = $source->apiChaptersUrl().'?page='.$page.'&per_page='.$perPage.'&orderby=desc_created';
        $result = $this->readJson($url, $host, $address, $port);

        if ($result['status'] === 404) {
            return [];
        }

        $chapters = $this->chapterEntries($result['json']['chapters'] ?? null, $perPage);

        if ($result['status'] !== 200 || $chapters === null) {
            throw new EditorialSourceVerificationException('La fonte non ha restituito un elenco di capitoli valido. Riprova o verifica l’API.');
        }

        return $chapters;
    }

    /** @return array<int, array{id: int, url: string, thumbnail: string, number: int}> */
    public function chapterImages(EditorialContent $content): array
    {
        $source = $content->source;
        $id = filter_var($content->external_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! $id || $source->site_id !== $content->site_id || ! $source->is_active
            || $source->verification_status !== 'verified' || ! $source->containsPublicUrl($content->public_url)) {
            throw new EditorialSourceVerificationException('Per leggere le immagini serve un capitolo di una fonte attiva e verificata.');
        }

        [$host, $address, $port] = $this->safeTarget($source);
        $result = $this->readJson($source->base_url.'/api/reader/chapter/format/json?id='.$id, $host, $address, $port);
        $data = $result['json'];

        if ($result['status'] !== 200 || (int) data_get($data, 'chapter.id') !== $id
            || (int) data_get($data, 'chapter.hidden', 1) !== 0 || (int) data_get($data, 'comic.hidden', 1) !== 0
            || (int) data_get($data, 'comic.adult', 1) !== 0 || ! is_array($data['pages'] ?? null)) {
            throw new EditorialSourceVerificationException('La fonte non ha restituito pagine pubbliche idonee per questo capitolo.');
        }

        $images = [];

        foreach (array_values($data['pages']) as $index => $page) {
            if (! is_array($page)) {
                continue;
            }

            $pageId = filter_var($page['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $url = $page['url'] ?? null;
            $thumb = $page['thumb_url'] ?? null;

            if (! $pageId || (int) ($page['chapter_id'] ?? 0) !== $id || (int) ($page['hidden'] ?? 1) !== 0
                || ! is_string($url) || ! $source->containsPublicUrl($url)
                || ! in_array($page['mime'] ?? null, self::IMAGE_TYPES, true)
                || (int) ($page['size'] ?? 0) > self::MAX_IMAGE_BYTES) {
                continue;
            }

            $images[$pageId] = [
                'id' => $pageId,
                'url' => $url,
                'thumbnail' => is_string($thumb) && $source->containsPublicUrl($thumb) ? $thumb : $url,
                'number' => $index + 1,
            ];
        }

        return $images;
    }

    public function readChapterImage(EditorialSource $source, string $url): string
    {
        if (! $source->is_active || $source->verification_status !== 'verified' || ! $source->containsPublicUrl($url)) {
            throw new EditorialSourceVerificationException('L’immagine deve appartenere alla fonte attiva e verificata del capitolo.');
        }

        [$host, $address, $port] = $this->safeTarget($source);
        $response = $this->read($url, $host, $address, $port, 'image/*', self::MAX_IMAGE_BYTES);
        $body = $response->body();
        $image = $response->ok() ? @getimagesizefromstring($body) : false;

        if (! $image || ! in_array($image['mime'], self::IMAGE_TYPES, true)) {
            throw new EditorialSourceVerificationException('La fonte non ha restituito un’immagine JPEG, PNG o WebP valida.');
        }

        return $body;
    }

    /** @return array<int, array<string, mixed>>|null */
    private function chapterEntries(mixed $raw, int $limit): ?array
    {
        if (! is_array($raw) || count($raw) > $limit) {
            return null;
        }

        foreach ($raw as $key => $entry) {
            // PHP can encode a filtered, sparse numeric array as a JSON object.
            if ((! is_int($key) && (! is_string($key) || ! ctype_digit($key)))
                || ! is_array($entry)
                || ! is_array($entry['comic'] ?? null)
                || ! is_array($entry['chapter'] ?? null)) {
                return null;
            }
        }

        return array_values($raw);
    }

    /** @return array{string, string, int} */
    private function safeTarget(EditorialSource $source): array
    {
        if ($source->provider !== 'foolslide') {
            throw new EditorialSourceVerificationException('Il tipo di fonte non è supportato dalla verifica.');
        }

        $parts = parse_url($source->base_url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new EditorialSourceVerificationException('Per verificare la fonte serve un URL valido.');
        }

        if ($this->localUrl->isAllowed($source->base_url)) {
            if (! defined('CURLOPT_RESOLVE')) {
                throw new EditorialSourceVerificationException('La verifica richiede l’estensione PHP cURL sul server.');
            }

            return ['localhost', '127.0.0.1', $parts['port']];
        }

        if ($parts['scheme'] !== 'https') {
            throw new EditorialSourceVerificationException('Per verificare la fonte serve un URL HTTPS pubblico; HTTP su localhost è consentito solo in locale.');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw new EditorialSourceVerificationException('Usa un URL HTTPS senza credenziali, query o porte personalizzate.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        if ($host !== strtolower($source->site->host)) {
            throw new EditorialSourceVerificationException('La fonte non appartiene più al dominio del sito selezionato.');
        }

        if (! defined('CURLOPT_RESOLVE')) {
            throw new EditorialSourceVerificationException('La verifica richiede l’estensione PHP cURL sul server.');
        }

        return [$host, $this->resolver->resolve($host), 443];
    }

    /** @return array{status: int, json: array<string, mixed>|null} */
    private function readJson(string $url, string $host, string $address, int $port): array
    {
        $response = $this->read($url, $host, $address, $port, 'application/json', self::MAX_RESPONSE_BYTES);
        $json = $response->json();

        return [
            'status' => $response->status(),
            'json' => is_array($json) && ! array_is_list($json) ? $json : null,
        ];
    }

    private function read(string $url, string $host, string $address, int $port, string $accept, int $maxBytes): Response
    {
        $pinnedAddress = str_contains($address, ':') ? '['.$address.']' : $address;

        try {
            $response = Http::accept($accept)->connectTimeout(3)->timeout(8)
                ->withOptions([
                    'allow_redirects' => false,
                    'http_errors' => false,
                    'proxy' => '',
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinnedAddress}"]],
                    'on_headers' => static function (ResponseInterface $headers) use ($maxBytes): void {
                        if ((int) $headers->getHeaderLine('Content-Length') > $maxBytes) {
                            throw new EditorialSourceVerificationException('La risposta della fonte supera la dimensione consentita.');
                        }
                    },
                    'progress' => static function (int $total, int $downloaded) use ($maxBytes): void {
                        if (max($total, $downloaded) > $maxBytes) {
                            throw new EditorialSourceVerificationException('La risposta della fonte supera la dimensione consentita.');
                        }
                    },
                ])->get($url);
        } catch (ConnectionException|TransferException) {
            throw new EditorialSourceVerificationException('La fonte non è raggiungibile. Riprova più tardi.');
        }

        if ((int) $response->header('Content-Length') > $maxBytes || strlen($response->body()) > $maxBytes) {
            throw new EditorialSourceVerificationException('La risposta della fonte supera la dimensione consentita.');
        }

        return $response;
    }

    private function verified(EditorialSource $source, string $message): bool
    {
        $source->update([
            'verification_status' => 'verified',
            'verification_message' => $message,
            'last_checked_at' => now(),
            'last_verified_at' => now(),
        ]);

        return true;
    }

    private function failed(EditorialSource $source, string $message): bool
    {
        $source->update([
            'verification_status' => 'failed',
            'verification_message' => $message,
            'last_checked_at' => now(),
        ]);

        return false;
    }
}
