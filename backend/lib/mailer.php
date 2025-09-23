<?php

// Ceci est une simulation de PHPMailer pour le développement local sans serveur SMTP.
// Dans un environnement de production, on utiliserait la vraie librairie PHPMailer.
// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;

class SimulatedMailer
{
    public $Host;
    public $Port;
    public $Username;
    public $Password;
    public $SMTPSecure;
    public $SMTPAuth;

    private $fromAddress;
    private $fromName;
    private $to = [];
    private $subject;
    private $body;

    public function __construct(array $config)
    {
        $this->Host = $config['MAIL_HOST'] ?? 'smtp.example.com';
        $this->Port = $config['MAIL_PORT'] ?? 587;
        $this->Username = $config['MAIL_USERNAME'] ?? '';
        $this->Password = $config['MAIL_PASSWORD'] ?? '';
        $this->SMTPSecure = $config['MAIL_ENCRYPTION'] ?? 'tls';
        $this->fromAddress = $config['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com';
        $this->fromName = $config['MAIL_FROM_NAME'] ?? 'inouttracker';
        $this->SMTPAuth = true;
    }

    public function setFrom($address, $name = '')
    {
        $this->fromAddress = $address;
        $this->fromName = $name;
    }

    public function addAddress($address)
    {
        $this->to[] = $address;
    }

    public function isHTML($isHtml)
    {
        // Ignoré dans la simulation
    }

    public function Subject($subject)
    {
        $this->subject = $subject;
    }

    public function Body($body)
    {
        $this->body = $body;
    }

    public function AltBody($altBody)
    {
        // Ignoré dans la simulation
    }

    /**
     * Simule l'envoi d'un email en l'écrivant dans un fichier de log.
     * @return bool
     */
    public function send(): bool
    {
        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/sent_emails.log';

        $logContent = "--- EMAIL SENT AT " . date('Y-m-d H:i:s') . " ---\n";
        $logContent .= "From: {$this->fromName} <{$this->fromAddress}>\n";
        $logContent .= "To: " . implode(', ', $this->to) . "\n";
        $logContent .= "Subject: {$this->subject}\n";
        $logContent .= "Body:\n{$this->body}\n";
        $logContent .= "--------------------------------------------------\n\n";

        return file_put_contents($logFile, $logContent, FILE_APPEND) !== false;
    }
}

/**
 * Envoie un email de réinitialisation de mot de passe.
 *
 * @param array $config La configuration de l'application.
 * @param string $recipientEmail L'email du destinataire.
 * @param string $resetToken Le token de réinitialisation.
 * @return bool
 */
function send_password_reset_email(array $config, string $recipientEmail, string $resetToken): bool
{
    $mailer = new SimulatedMailer($config);

    $resetLink = rtrim($config['APP_URL'], '/') . '/#reset=' . $resetToken;

    $mailer->setFrom($config['MAIL_FROM_ADDRESS'], $config['MAIL_FROM_NAME']);
    $mailer->addAddress($recipientEmail);
    $mailer->Subject = 'Réinitialisation de votre mot de passe';
    $mailer->isHTML(true);
    $mailer->Body = "
        <p>Bonjour,</p>
        <p>Vous avez demandé une réinitialisation de votre mot de passe. Cliquez sur le lien ci-dessous pour continuer :</p>
        <p><a href='{$resetLink}'>{$resetLink}</a></p>
        <p>Ce lien expirera dans une heure.</p>
        <p>Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email.</p>
    ";

    return $mailer->send();
}
