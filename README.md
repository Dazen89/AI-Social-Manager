# Monitoring Hub

Dashboard Laravel privata per registrare siti posseduti o amministrati, collegare servizi di monitoraggio e consultare dati verificati in un’unica interfaccia. Google Analytics 4 è la prima integrazione disponibile.

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

## Collegamento Google Analytics 4

L’integrazione usa OAuth 2.0 con il solo scope di lettura `analytics.readonly`. Credenziali dell’applicazione, access token e refresh token vengono cifrati usando la chiave dell’applicazione prima di essere salvati nel database.

Per configurare una nuova installazione:

1. Apri **Impostazioni → Google Analytics** nella dashboard e mantienila aperta come guida.
2. Crea o seleziona un progetto in Google Cloud e abilita **Google Analytics Admin API**.
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
- [Elenco account e proprietà accessibili](https://developers.google.com/analytics/devguides/config/admin/v1/rest/v1beta/accountSummaries/list)

## Flusso utente

1. Accedi come amministratore.
2. Registra un sito indicando nome e dominio.
3. Se richiesto, completa una sola volta la procedura in **Impostazioni → Google Analytics**.
4. Apri **Gestisci → Servizi e integrazioni**.
5. Seleziona **Collega account Google** e autorizza l’accesso in sola lettura.
6. Scegli una proprietà dall’elenco verificato restituito da Google.

Se esiste uno stream web con lo stesso dominio del sito, l’applicazione salva automaticamente anche Data Stream ID e Measurement ID. In caso contrario la proprietà resta collegata e viene mostrato un avviso esplicito.

## Verifiche

```bash
php artisan test
vendor/bin/pint --test
php artisan view:cache
```

I test usano risposte HTTP simulate: non effettuano autorizzazioni reali e non modificano dati Google Analytics.

## Sicurezza

- Non commettere `.env`, token, credenziali OAuth, database, log o dati reali.
- Usa HTTPS in produzione e limita l’accesso alla dashboard agli amministratori autorizzati.
- Conserva `APP_KEY`: cambiarla rende illeggibili le credenziali e i token già cifrati.
- Esegui un backup prima delle migrazioni di produzione e usa `php artisan migrate:rollback --step=1` solo dopo aver verificato l’impatto sui dati.

Il contesto funzionale e le milestone sono documentati in [`PROJECT_CONTEXT.md`](PROJECT_CONTEXT.md).
