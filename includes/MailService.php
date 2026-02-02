<?php
require_once __DIR__ . '/functions.php';

class MailService
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption;
    private $fromEmail;
    private $fromName;
    private $debug = [];

    public function __construct($config = null)
    {
        if ($config) {
            $this->host = $config['host'] ?? '';
            $this->port = $config['port'] ?? 587;
            $this->username = $config['username'] ?? '';
            $this->password = $config['password'] ?? '';
            $this->encryption = $config['encryption'] ?? 'tls';
            $this->fromName = $config['from_name'] ?? getSetting('site_name');
            $this->fromEmail = $config['from_email'] ?? $this->username;
        } else {
            $this->host = getSetting('smtp_host');
            $this->port = getSetting('smtp_port', 587);
            $this->username = getSetting('smtp_username');
            $this->password = getSetting('smtp_password');
            $this->encryption = getSetting('smtp_encryption', 'tls'); // tls, ssl, or none
            $this->fromName = getSetting('smtp_from_name', getSetting('site_name'));
            $this->fromEmail = getSetting('smtp_username'); // Usually same as username
        }
    }

    public function send($to, $subject, $message, $isHtml = true)
    {
        if (empty($this->host) || empty($this->username) || empty($this->password)) {
            $this->log("SMTP settings not configured.");
            return false;
        }

        try {
            $socket = null;
            $protocol = '';

            if ($this->encryption === 'ssl') {
                $protocol = 'ssl://';
            }

            $socket = fsockopen($protocol . $this->host, $this->port, $errno, $errstr, 10);

            if (!$socket) {
                throw new Exception("Connection failed: $errno - $errstr");
            }

            $this->read($socket); // Initial greeting

            $this->cmd($socket, 'EHLO ' . $_SERVER['SERVER_NAME']);

            if ($this->encryption === 'tls') {
                $this->cmd($socket, 'STARTTLS');
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->cmd($socket, 'EHLO ' . $_SERVER['SERVER_NAME']);
            }

            $this->cmd($socket, 'AUTH LOGIN');
            $this->cmd($socket, base64_encode($this->username));
            $this->cmd($socket, base64_encode($this->password));

            $this->cmd($socket, 'MAIL FROM: <' . $this->username . '>');
            $this->cmd($socket, 'RCPT TO: <' . $to . '>');
            $this->cmd($socket, 'DATA');

            $boundary = md5(uniqid(time()));

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <" . $this->fromEmail . ">\r\n";
            $headers .= "To: <" . $to . ">\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "Date: " . date("r") . "\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n";

            $body = chunk_split(base64_encode($message));

            $content = $headers . "\r\n" . $body . "\r\n.\r\n";

            $result = $this->cmd($socket, $content, true); // End data with .

            $this->cmd($socket, 'QUIT');
            fclose($socket);

            return true;

        } catch (Exception $e) {
            $this->log("Error: " . $e->getMessage());
            return false;
        }
    }

    private function cmd($socket, $command, $isData = false)
    {
        if (!$isData) {
            fputs($socket, $command . "\r\n");
            return $this->read($socket);
        } else {
            fputs($socket, $command);
            return $this->read($socket);
        }
    }

    private function read($socket)
    {
        $response = '';
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == ' ') {
                break;
            }
        }
        $this->log("Server: $response");
        return $response;
    }

    private function log($msg)
    {
        // You can implement file logging here if needed
        // file_put_contents(__DIR__ . '/../smtp_log.txt', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
    }
}

// Helper function for global use
function sendEmail($to, $subject, $body)
{
    try {
        $mailer = new MailService();
        return $mailer->send($to, $subject, $body);
    } catch (Throwable $e) {
        return false;
    }
}
