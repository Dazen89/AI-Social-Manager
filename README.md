# Monitoring Hub

Dashboard Laravel privata per registrare siti posseduti o amministrati, collegare servizi di monitoraggio, configurare un fornitore AI e preparare la pubblicazione social da un’unica interfaccia. Google Analytics 4, Google Search Console, OpenAI, Anthropic e X sono le prime integrazioni disponibili.

## Requisiti

- PHP 8.3 o successivo
- Composer
- Node.js e npm
- SQLite per lo sviluppo locale oppure MySQL in produzione

## Installazione locale

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

Prima di eseguire il seeder, configura in `.env` il primo amministratore:

```dotenv
INITIAL_ADMIN_NAME="Nome amministratore"
INITIAL_ADMIN_EMAIL="admin@example.com"
INITIAL_ADMIN_PASSWORD="una-password-di-almeno-12-caratteri"
```

Il repository non prevede registrazione pubblica. Il seeder crea il primo amministratore e non sovrascrive la password di un utente già esistente.

## Aggiornamento della VPS

Su una VPS Linux già configurata, l’intero aggiornamento può essere eseguito dalla cartella del progetto con un solo comando:

```bash
bash scripts/deploy.sh
```

Se il progetto è un normale checkout Git, lo script aggiorna automaticamente il branch tracciato. Se invece i file sono distribuiti da un pannello hosting come Plesk e la cartella pubblicata non contiene `.git`, lo script riconosce il contesto e salta il `git pull`: in questo caso aggiorna prima i file dal pannello Plesk e poi esegui lo stesso comando nella cartella di destinazione.

Lo script:

1. quando è presente un checkout Git, interrompe l’operazione se contiene modifiche locali o se il branch non ha un upstream;
2. mette temporaneamente Laravel in modalità manutenzione;
3. esegue `git pull --ff-only` senza creare merge automatici, oppure lo salta nelle cartelle distribuite da Plesk;
4. installa le dipendenze PHP di produzione;
5. applica le migrazioni con `php artisan migrate --force`;
6. esegue `npm ci` e `npm run build`;
7. pulisce le cache Laravel e ricompila le viste;
8. riporta l’applicazione online anche se un passaggio fallisce.

La VPS deve avere `git`, PHP, Composer, Node.js e npm disponibili per l’utente di deploy. Lo script non contiene credenziali, non modifica `.env` e non richiede privilegi `root`. Prima di migrazioni importanti esegui un backup del database. Per tornare a una revisione precedente, prepara un normale commit di revert e ridistribuiscilo; esegui `migrate:rollback` solo dopo aver verificato esplicitamente l’impatto sui dati.

## Collegamento Google Analytics 4

L’integrazione usa OAuth 2.0 con il solo scope di lettura `analytics.readonly`. Credenziali dell’applicazione, access token e refresh token vengono cifrati usando la chiave dell’applicazione prima di essere salvati nel database.

Per configurare una nuova installazione:

1. Apri **Impostazioni → Google Analytics** nella dashboard e mantienila aperta come guida.
2. Crea o seleziona un progetto in Google Cloud e abilita sia **Google Analytics Admin API** sia **Google Analytics Data API**. La prima scopre account, proprietà e stream; la seconda legge i report della panoramica.
3. In **Google Auth Platform → Branding** configura nome applicazione, email di assistenza, pubblico ed email di contatto.
4. Se il pubblico è **Esterno** e l’app è in modalità **Test**, aggiungi in **Pubblico → Utenti di test** gli account Google che potranno collegare Analytics. I refresh token delle app esterne in modalità Test possono scadere dopo sette giorni; per l’uso continuativo occorre passare in produzione e seguire le eventuali richieste di verifica di Google.
5. In **Accesso ai dati** aggiungi lo scope di sola lettura `https://www.googleapis.com/auth/analytics.readonly`.
6. In **Client**, crea un client OAuth di tipo **Applicazione web**. Non usare una Chiave API o un Service account.
7. Lascia vuote le origini JavaScript e copia come **URI di reindirizzamento autorizzato** l’indirizzo esatto mostrato dalla dashboard. Normalmente è:

   ```text
   https://dashboard.example.com/integrations/google-analytics/callback
   ```

8. Copia Client ID e Client Secret da Google Cloud nel modulo della dashboard e salva. Il Client Secret viene cifrato e non viene più mostrato.

Per installazioni gestite da personale tecnico, è disponibile anche un override opzionale tramite ambiente:

   ```dotenv
   GOOGLE_ANALYTICS_CLIENT_ID=
   GOOGLE_ANALYTICS_CLIENT_SECRET=
   GOOGLE_ANALYTICS_REDIRECT_URI="${APP_URL}/integrations/google-analytics/callback"
   ```

Le variabili d’ambiente hanno la precedenza sui valori nel database e rendono il modulo della dashboard di sola lettura. Se la configurazione Laravel è in cache, dopo una modifica esegui:

   ```bash
   php artisan config:clear
   ```

Il redirect URI deve coincidere esattamente con quello registrato in Google Cloud, inclusi protocollo, dominio, porta e slash finale. La dashboard usa l’indirizzo configurato dall’installazione tramite `APP_URL` oppure, se presente, `GOOGLE_ANALYTICS_REDIRECT_URI`: non sostituisce il dominio e non impone `localhost`. L’accettazione finale dell’URI dipende dalle regole applicate da Google Cloud. Le credenziali OAuth appartengono all’intera installazione: non devono essere inserite nei form dei singoli siti né aggiunte al repository.

Documentazione ufficiale:

- [OAuth 2.0 per applicazioni web](https://developers.google.com/identity/protocols/oauth2/web-server)
- [Google Analytics Admin API](https://developers.google.com/analytics/devguides/config/admin/v1)
- [Google Analytics Data API](https://developers.google.com/analytics/devguides/reporting/data/v1)
- [Elenco account e proprietà accessibili](https://developers.google.com/analytics/devguides/config/admin/v1/rest/v1beta/accountSummaries/list)
- [Report multipli in una richiesta](https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/properties/batchRunReports)

## Collegamento Google Search Console

Search Console è un’integrazione opzionale per sito. Usa OAuth 2.0 con il solo scope `webmasters.readonly`; credenziali, access token e refresh token vengono cifrati prima del salvataggio. Questa prima versione collega e verifica la proprietà: l’importazione di query, clic, impressioni, CTR e posizione media verrà aggiunta al livello di reporting in un incremento successivo.

La procedura completa e i valori esatti da copiare sono disponibili in **Gestisci sito → Google Search Console → Configura Search Console**. In sintesi:

1. verifica che l’account Google possa accedere al sito in [Google Search Console](https://search.google.com/search-console);
2. abilita la **Google Search Console API** nel progetto Google Cloud;
3. completa la configurazione di Google Auth Platform e, se l’app è in modalità Test, aggiungi l’account tra gli utenti di test;
4. aggiungi lo scope `https://www.googleapis.com/auth/webmasters.readonly`;
5. crea un client OAuth “Applicazione web”, oppure riutilizza quello di Analytics, e registra il callback esatto mostrato dalla dashboard;
6. salva Client ID e Client Secret nella dashboard, autorizza l’account e scegli la proprietà proposta per il dominio.

Se si riutilizza il client OAuth di Analytics, occorre aggiungere anche il callback Search Console senza rimuovere quello Analytics. La dashboard conserva separatamente proprietà e token, ma Google gestisce consenso e revoca a livello di progetto Cloud: revocare un collegamento può rendere necessaria una nuova autorizzazione anche per gli altri scope dello stesso progetto. Per un isolamento completo usa progetti Google Cloud distinti.

Override opzionale per installazioni gestite tramite ambiente:

```dotenv
GOOGLE_SEARCH_CONSOLE_CLIENT_ID=
GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET=
GOOGLE_SEARCH_CONSOLE_REDIRECT_URI="${APP_URL}/integrations/google-search-console/callback"
```

Documentazione ufficiale:

- [Autorizzazione Search Console API](https://developers.google.com/webmaster-tools/v1/how-tos/authorizing)
- [Elenco delle proprietà accessibili](https://developers.google.com/webmaster-tools/v1/sites/list)
- [Search Analytics query](https://developers.google.com/webmaster-tools/v1/searchanalytics/query)
- [Quote e limiti](https://developers.google.com/webmaster-tools/limits)

## Integrazione AI

Dalla dashboard apri **Integrazioni AI** per vedere i collegamenti già configurati oppure crearne uno nuovo. Per ogni integrazione scegli il fornitore (OpenAI o Anthropic), il modello e inserisci una API key dedicata. La pagina di creazione contiene i collegamenti alle console e alle guide ufficiali per creare la chiave e scegliere un modello disponibile per il proprio account.

Per OpenAI, il codice richiesto all’apertura della console è un codice di verifica del login all’account: non è la API key e non va inserito nel form di Monitoring Hub. Completa il login solo con un account e un metodo di verifica riconosciuti; nella pagina del progetto crea poi una nuova chiave segreta, copiala e incollala nel campo **API key**. La guida usa il percorso diretto della console per le chiavi di progetto.

Il selettore OpenAI propone le famiglie general-purpose attive più utili per la generazione testuale, incluse GPT-6, GPT-5.6, GPT-5.5 e GPT-5.4 Mini (`gpt-5.4-mini`). Modelli specialistici o già deprecati non vengono mostrati.

Le integrazioni appartengono all’intera installazione e sono salvate nella tabella `ai_integrations`; le API key sono cifrate e non vengono mai mostrate dopo il salvataggio. Una sola integrazione è attiva alla volta: dal riepilogo un amministratore può effettuare lo switch, mentre la prima integrazione creata viene attivata automaticamente. `App\Services\AiService` usa esclusivamente l’integrazione attiva ed espone un adapter comune per la generazione di testo:

- OpenAI Responses API (`POST /v1/responses`);
- Anthropic Messages API (`POST /v1/messages`).

Dopo il salvataggio, il riepilogo mostra lo stato **Da verificare**. Il pulsante **Verifica** esegue una richiesta di sola lettura a `GET /v1/models`, controlla che il modello scelto sia disponibile per la chiave e mostra **Chiave e modello verificati** oppure **Verifica non riuscita**. Questa verifica non genera contenuti e quindi non conferma che il progetto disponga di credito o budget per una generazione.

Quando Google Analytics e l’integrazione AI attiva sono entrambi collegati e verificati, la pagina del sito espone **Analizza con AI**. Il flusso confronta il periodo selezionato con quello precedente della stessa durata, prepara metriche aggregate su traffico, landing page, canali e dispositivi e richiede una risposta strutturata con criticità, opportunità, evidenze e prossime azioni. I report e lo snapshot aggregato sono cifrati nel database; le query string delle landing page vengono rimosse prima dell’invio. Se i dati e il modello non cambiano, l’analisi salvata viene riutilizzata senza una nuova chiamata a consumo.

Ogni analisi registra separatamente numero di richieste e token in ingresso/uscita nella tabella `ai_usage_records`, senza copiare prompt o credenziali. Questi valori descrivono il consumo ma non rappresentano un costo monetario esatto. OpenAI espone il costo ufficiale dell’organizzazione con una Costs API amministrativa che richiede una Admin API key distinta dalla normale chiave di progetto; Monitoring Hub non richiede tale credenziale privilegiata e rimanda al pannello di fatturazione del provider.

I test usano `Http::fake()` e non effettuano chiamate a consumo. Prima di collegare un flusso applicativo in produzione, configura budget e limiti presso il fornitore e conserva stabilmente `APP_KEY`, necessaria per decifrare le credenziali.

Documentazione ufficiale:

- [Quickstart API OpenAI](https://developers.openai.com/api/docs/quickstart)
- [Modelli OpenAI](https://developers.openai.com/api/docs/models)
- [Costs API OpenAI](https://developers.openai.com/api/reference/resources/admin/subresources/organization/subresources/usage/methods/costs)
- [Creare una API key Claude](https://platform.claude.com/docs/en/get-api-key)
- [Modelli Claude](https://platform.claude.com/docs/en/models/overview)

## Collegamento dell’account X

La pagina **Social** guida un amministratore nella configurazione di un’app X e nel collegamento dell’unico account che pubblicherà per questa installazione. Il flusso usa OAuth 2.0 Authorization Code con PKCE e richiede soltanto `tweet.read`, `tweet.write`, `users.read` e `offline.access`. Client Secret, access token e refresh token sono cifrati nel database; password, email e messaggi privati dell’account non vengono richiesti.

Per configurare il collegamento:

1. completa il **Developer Agreement & Policy** descrivendo nel campo “Describe all of your use cases of X’s data and API” gli usi reali e previsti dell’installazione; la pagina **Social** fornisce un testo di esempio da adattare, non una dichiarazione da inviare senza verifica;
2. crea un progetto e un’app nel [Developer Console di X](https://developer.x.com/en/portal/dashboard) con un piano che includa la creazione di post;
3. in **User authentication settings** abilita OAuth 2.0 e scegli **Web App, Automated App or Bot**;
4. abilita i permessi **Read and write**;
5. registra come Callback URI l’indirizzo esatto mostrato nella pagina **Social**;
6. recupera dalla Developer Console il **Client ID** e il **Client Secret** OAuth 2.0 e copiali nella dashboard. Non usare API Key, API Key Secret o Bearer Token;
7. salva, usa **Collega account X**, accedi all’account corretto e conferma i permessi: l’app legge `/2/users/me` per mostrare e verificare l’account scelto.

La Developer Console è quindi la fonte per approvazione del caso d’uso, piano e quote, progetto/app, Client ID, Client Secret, permessi e stato dell’app. Monitoring Hub fornisce invece il Callback URI e l’elenco degli scope da registrare; l’identità dell’account di pubblicazione viene recuperata direttamente da X dopo il consenso.

Per installazioni gestite da personale tecnico sono disponibili override opzionali tramite ambiente:

```dotenv
X_CLIENT_ID=
X_CLIENT_SECRET=
X_REDIRECT_URI="${APP_URL}/integrations/x/callback"
```

Le variabili d’ambiente hanno precedenza sulla configurazione cifrata della dashboard. Il callback deve coincidere esattamente con quello registrato presso X. Lo scollegamento prova a revocare il token presso X e rimuove sempre la copia locale; se la revoca remota non riesce, la dashboard invita a rimuovere l’app anche dalle impostazioni di sicurezza dell’account.

Questa integrazione prepara l’autorizzazione di pubblicazione ma non invia post durante il setup. Editor, approvazione dei contenuti, pianificazione, job idempotenti e storico delle consegne verranno aggiunti separatamente prima di abilitare scritture automatiche reali.

Documentazione ufficiale:

- [OAuth 2.0 Authorization Code con PKCE](https://docs.x.com/fundamentals/authentication/oauth-2-0/authorization-code)
- [Creazione di post con X API](https://docs.x.com/x-api/posts/create-post)
- [Panoramica dell’autenticazione OAuth 2.0](https://docs.x.com/fundamentals/authentication/oauth-2-0/overview)
- [Developer Agreement](https://docs.x.com/developer-terms/agreement)
- [Developer Policy](https://docs.x.com/developer-terms/policy)

## Flusso utente

1. Accedi come amministratore.
2. Registra un sito indicando nome e dominio.
3. Se richiesto, completa una sola volta le procedure guidate Google Analytics e/o Google Search Console.
4. Apri **Gestisci → Servizi e integrazioni**.
5. Seleziona **Collega account Google** e autorizza l’accesso in sola lettura.
6. Scegli per ogni servizio una proprietà dall’elenco verificato restituito da Google.
7. Torna a **Siti** e apri la panoramica del sito per consultare i report degli ultimi 7, 28 o 90 giorni e, facoltativamente, generarne l’analisi AI.

Se esiste uno stream web con lo stesso dominio del sito, l’applicazione salva automaticamente anche Data Stream ID e Measurement ID. In caso contrario la proprietà resta collegata e viene mostrato un avviso esplicito. La panoramica interroga la Data API su richiesta, filtra i report per l’hostname registrato e conserva la risposta in cache per dieci minuti per ridurre latenza e consumo di quota.

## Verifiche

```bash
php artisan test
vendor/bin/pint --test
php artisan view:cache
```

I test usano risposte HTTP simulate: non effettuano autorizzazioni reali, non modificano dati Google Analytics o Search Console e non consumano API AI. La suite accetta esclusivamente `APP_ENV=testing` con SQLite `:memory:`; `tests/TestCase.php` interrompe l’avvio prima di `RefreshDatabase` se rileva qualsiasi database persistente. `phpunit.xml` forza inoltre questi valori e usa un percorso di cache della configurazione dedicato ai test.

## Sicurezza

- Non commettere `.env`, token, credenziali OAuth, database, log o dati reali.
- Usa HTTPS in produzione e limita l’accesso alla dashboard agli amministratori autorizzati.
- Conserva `APP_KEY`: cambiarla rende illeggibili le credenziali e i token già cifrati.
- Esegui un backup prima delle migrazioni di produzione e usa `php artisan migrate:rollback --step=1` solo dopo aver verificato l’impatto sui dati.

Il contesto funzionale e le milestone sono documentati in [`PROJECT_CONTEXT.md`](PROJECT_CONTEXT.md).
