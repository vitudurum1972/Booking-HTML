<?php
/**
 * Schlanker SMTP-Mailer für das Gemeindeportal Wangen-Brüttisellen.
 * Unterstützt STARTTLS (Port 587) und direkte TLS (Port 465).
 * Getestet mit Microsoft 365 (smtp.office365.com).
 *
 * Verwendung:
 *   $mailer = new SmtpMailer('smtp.office365.com', 587, 'user@domain.ch', 'passwort');
 *   $mailer->send('empfaenger@domain.ch', 'Betreff', '<h1>HTML-Inhalt</h1>');
 */
class SmtpMailer
{
    private string $host;
    private int    $port;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;
    private int    $timeout;
    private bool   $debug;

    /** @var resource|null */
    private $socket = null;
    private string $lastError = '';
    private array  $log = [];

    public function __construct(
        string $host,
        int    $port,
        string $username,
        string $password,
        string $fromEmail = '',
        string $fromName  = '',
        int    $timeout   = 15,
        bool   $debug     = false
    ) {
        $this->host      = $host;
        $this->port      = $port;
        $this->username  = $username;
        $this->password  = $password;
        $this->fromEmail = $fromEmail ?: $username;
        $this->fromName  = $fromName;
        $this->timeout   = $timeout;
        $this->debug     = $debug;
    }

    /**
     * HTML-Mail versenden.
     *
     * @param  string|array $to          Empfänger (String oder Array für mehrere)
     * @param  string       $subject     Betreff
     * @param  string       $htmlBody    HTML-Inhalt
     * @param  string|null  $icsContent  Optionaler iCalendar-Inhalt (VCALENDAR)
     * @param  string       $icsMethod   iCalendar-Methode: REQUEST, CANCEL, PUBLISH (Standard: REQUEST)
     * @return bool
     */
    public function send($to, string $subject, string $htmlBody, ?string $icsContent = null, string $icsMethod = 'REQUEST'): bool
    {
        $this->lastError = '';
        $this->log       = [];

        if (is_string($to)) {
            $to = [$to];
        }

        try {
            $this->connect();
            $this->ehlo();
            $this->startTls();
            $this->ehlo();
            $this->authenticate();

            // MAIL FROM
            $this->command('MAIL FROM:<' . $this->fromEmail . '>', 250);

            // RCPT TO
            foreach ($to as $recipient) {
                $this->command('RCPT TO:<' . trim($recipient) . '>', 250);
            }

            // DATA
            $this->command('DATA', 354);

            $message = $this->buildMessage($to, $subject, $htmlBody, $icsContent, $icsMethod);
            $this->sendData($message);
            $this->command('.', 250);

            // QUIT
            $this->command('QUIT', 221);
            $this->close();

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            error_log('[SmtpMailer] Fehler: ' . $e->getMessage());
            $this->close();
            return false;
        }
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getLog(): array
    {
        return $this->log;
    }

    // ── Interne Methoden ─────────────────────────────────────

    private function connect(): void
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);

        // Port 465 = direktes TLS, Port 587 = STARTTLS
        $prefix = ($this->port === 465) ? 'ssl://' : '';
        $addr   = $prefix . $this->host . ':' . $this->port;

        $this->socket = @stream_socket_client(
            $addr,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new \Exception("Verbindung zu {$addr} fehlgeschlagen: [{$errno}] {$errstr}");
        }

        stream_set_timeout($this->socket, $this->timeout);
        $this->readResponse(220);
    }

    private function ehlo(): void
    {
        $hostname = gethostname() ?: 'localhost';
        $this->command('EHLO ' . $hostname, 250);
    }

    private function startTls(): void
    {
        // Nur bei Port 587 (STARTTLS), bei 465 ist TLS schon aktiv
        if ($this->port === 465) {
            return;
        }

        $this->command('STARTTLS', 220);

        $crypto = stream_socket_enable_crypto(
            $this->socket,
            true,
            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
        );

        if (!$crypto) {
            throw new \Exception('STARTTLS-Verschlüsselung konnte nicht aktiviert werden.');
        }
    }

    private function authenticate(): void
    {
        // AUTH LOGIN
        $this->command('AUTH LOGIN', 334);
        $this->command(base64_encode($this->username), 334);
        $this->command(base64_encode($this->password), 235);
    }

    private function command(string $cmd, int $expectedCode): string
    {
        $this->log($cmd, '>');
        fwrite($this->socket, $cmd . "\r\n");
        return $this->readResponse($expectedCode);
    }

    private function sendData(string $data): void
    {
        // Zeilen die mit einem Punkt beginnen: doppelter Punkt (RFC 5321 §4.5.2)
        $lines = explode("\n", str_replace("\r\n", "\n", $data));
        foreach ($lines as $line) {
            if (isset($line[0]) && $line[0] === '.') {
                $line = '.' . $line;
            }
            fwrite($this->socket, $line . "\r\n");
        }
    }

    private function readResponse(int $expectedCode): string
    {
        $response = '';
        while (true) {
            $line = fgets($this->socket, 4096);
            if ($line === false) {
                throw new \Exception('Verbindung unterbrochen beim Lesen der Antwort.');
            }
            $response .= $line;
            $this->log(trim($line), '<');

            // Mehrzeilige Antworten: "250-..." weiter lesen, "250 ..." = Ende
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
            if (strlen($line) < 4) {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \Exception(
                "SMTP-Fehler: erwartet {$expectedCode}, erhalten {$code}. Antwort: " . trim($response)
            );
        }

        return $response;
    }

    private function buildMessage(array $to, string $subject, string $htmlBody, ?string $icsContent = null, string $icsMethod = 'REQUEST'): string
    {
        $date     = date('r');
        $msgId    = '<' . bin2hex(random_bytes(16)) . '@' . $this->host . '>';

        // From-Header
        $from = $this->fromName
            ? '=?UTF-8?B?' . base64_encode($this->fromName) . '?= <' . $this->fromEmail . '>'
            : $this->fromEmail;

        // Subject UTF-8 kodiert
        $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        // Gemeinsame Header
        $headers  = "Date: {$date}\r\n";
        $headers .= "From: {$from}\r\n";
        $headers .= "To: " . implode(', ', $to) . "\r\n";
        $headers .= "Subject: {$subjectEncoded}\r\n";
        $headers .= "Message-ID: {$msgId}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        // Plaintext-Version (einfacher Strip der HTML-Tags)
        $plaintext = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $htmlBody));
        $plaintext = html_entity_decode($plaintext, ENT_QUOTES, 'UTF-8');
        $plaintext = preg_replace("/\n{3,}/", "\n\n", trim($plaintext));

        $fullHtml = '<html><body style="font-family:Arial,sans-serif;">' . $htmlBody . '</body></html>';

        // Wenn kein ICS: einfache multipart/alternative-Mail wie bisher
        if ($icsContent === null || $icsContent === '') {
            $boundary = '----=_Alt_' . bin2hex(random_bytes(12));
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $headers .= "\r\n";

            $body  = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($plaintext)) . "\r\n";

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($fullHtml)) . "\r\n";

            $body .= "--{$boundary}--\r\n";

            return $headers . $body;
        }

        // Mit ICS: multipart/mixed → multipart/alternative (text+html+calendar inline) + Anhang
        $mixedBoundary = '----=_Mixed_' . bin2hex(random_bytes(12));
        $altBoundary   = '----=_Alt_'   . bin2hex(random_bytes(12));

        $method = strtoupper($icsMethod);
        $icsCrlf = preg_replace("/\r\n|\r|\n/", "\r\n", $icsContent);
        $icsB64  = chunk_split(base64_encode($icsCrlf));

        $headers .= "Content-Type: multipart/mixed; boundary=\"{$mixedBoundary}\"\r\n";
        $headers .= "\r\n";

        // Teil 1: multipart/alternative (plain + html + text/calendar inline)
        $body  = "--{$mixedBoundary}\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";

        $body .= "--{$altBoundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($plaintext)) . "\r\n";

        $body .= "--{$altBoundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($fullHtml)) . "\r\n";

        // text/calendar inline → Outlook rendert damit die Termin-Buttons
        $body .= "--{$altBoundary}\r\n";
        $body .= "Content-Type: text/calendar; method={$method}; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= $icsB64 . "\r\n";

        $body .= "--{$altBoundary}--\r\n";

        // Teil 2: application/ics als herunterladbarer Anhang "termin.ics"
        $body .= "--{$mixedBoundary}\r\n";
        $body .= "Content-Type: application/ics; name=\"termin.ics\"\r\n";
        $body .= "Content-Disposition: attachment; filename=\"termin.ics\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= $icsB64 . "\r\n";

        $body .= "--{$mixedBoundary}--\r\n";

        return $headers . $body;
    }

    private function close(): void
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    private function log(string $message, string $direction): void
    {
        if ($this->debug) {
            $this->log[] = $direction . ' ' . $message;
        }
    }
}
