<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Config\Mailer as MailerConfig;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Throwable;

class MailerService
{
    private MailerConfig $config;

    public function __construct(?MailerConfig $config = null)
    {
        $this->config = $config ?? MailerConfig::load();
    }

    /**
     * Renderiza una plantilla HTML reemplazando {{variable}} por sus valores.
     * Compatible con estructuras y etiquetas de Go templates: {{.Key}}, {{if .Key}}, {{if not .Key}}, {{printf ...}}.
     *
     * @param string $templateName Nombre del archivo en Templates (ej. "confirm_account.html")
     * @param array<string, mixed> $vars
     */
    public function renderTemplate(string $templateName, array $vars = []): string
    {
        $path = __DIR__ . '/../Templates/' . $templateName;
        if (!file_exists($path)) {
            $path = __DIR__ . '/../Templates/' . $templateName . '.html';
        }

        if (!file_exists($path)) {
            return '<p>' . ($vars['message'] ?? 'Notificación de Fitbase') . '</p>';
        }

        $html = file_get_contents($path);
        if ($html === false) {
            return '';
        }

        $html = preg_replace_callback('/\{\{if\s+(\.?\w+)\}\}(.*?)(?:\{\{else\}\}(.*?))?\{\{end\}\}/s', function ($m) use ($vars) {
            $key = ltrim($m[1], '.');
            $val = !empty($vars[$key]);
            return $val ? $m[2] : ($m[3] ?? '');
        }, $html);

        $html = preg_replace_callback('/\{\{if\s+not\s+(\.?\w+)\}\}(.*?)(?:\{\{else\}\}(.*?))?\{\{end\}\}/s', function ($m) use ($vars) {
            $key = ltrim($m[1], '.');
            $val = !empty($vars[$key]);
            return !$val ? $m[2] : ($m[3] ?? '');
        }, $html);

        $html = preg_replace_callback('/\{\{printf\s+"([^"]+)"\s+\.?(\w+)\}\}/', function ($m) use ($vars) {
            $format = $m[1];
            $key = $m[2];
            $val = isset($vars[$key]) ? (float) $vars[$key] : 0.0;
            return sprintf($format, $val);
        }, $html);

        foreach ($vars as $key => $val) {
            if (is_bool($val)) {
                $strVal = $val ? 'true' : 'false';
            } elseif (is_numeric($val) || is_string($val)) {
                $strVal = (string) $val;
            } else {
                $strVal = '';
            }
            $html = str_replace('{{.' . $key . '}}', $strVal, $html);
            $html = str_replace('{{' . $key . '}}', $strVal, $html);
        }

        return $html;
    }

    public function send(string $to, string $subject, string $bodyHtml, string $altBody = ''): bool
    {
        if (empty($this->config->smtpHost) || empty($this->config->smtpUser)) {
            error_log("[MailerService] Simulación de envío a {$to}: {$subject}");
            return true;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $this->config->smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config->smtpUser;
            $mail->Password   = $this->config->smtpPass ?: $this->config->smtpPassKey;
            $mail->SMTPSecure = $this->config->smtpPort === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $this->config->smtpPort;
            $mail->Timeout    = 5;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($this->config->fromEmail, $this->config->fromName);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;
            $mail->AltBody = $altBody ?: strip_tags($bodyHtml);

            return $mail->send();
        } catch (Throwable $e) {
            error_log("[MailerService] Falló el envío de correo a {$to}: " . $e->getMessage());
            return false;
        }
    }
}

