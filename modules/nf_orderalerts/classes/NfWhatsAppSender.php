<?php
/**
 * Sends a WhatsApp text to the owner's own number through CallMeBot
 * (https://www.callmebot.com/blog/free-api-whatsapp-messages/): free, activated by the
 * owner from his phone, no Meta business verification. To move to the official
 * WhatsApp Cloud API later, replace send() only; the module calls nothing else.
 *
 * Never throws: an alert must not break checkout. Failures go to the PrestaShop log.
 */
class NfWhatsAppSender
{
    const ENDPOINT = 'https://api.callmebot.com/whatsapp.php';
    const TIMEOUT_SECONDS = 8;

    /** @var string */
    private $phone;
    /** @var string */
    private $apiKey;

    public function __construct(string $phone, string $apiKey)
    {
        $this->phone = $phone;
        $this->apiKey = $apiKey;
    }

    /**
     * @return string|null error message, null on success
     */
    public function send(string $text): ?string
    {
        if ($this->phone === '' || $this->apiKey === '') {
            return 'Falta el número o la API key de WhatsApp.';
        }
        if (!function_exists('curl_init')) {
            return 'El servidor no tiene cURL.';
        }

        $url = self::ENDPOINT . '?' . http_build_query([
            'phone' => '+' . $this->phone,
            'text' => $text,
            'apikey' => $this->apiKey,
        ]);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $body = (string) curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError !== '') {
            return 'No se pudo conectar con WhatsApp: ' . $curlError;
        }
        // CallMeBot answers 200 with an HTML page; errors mention "APIKey" or "ERROR".
        if ($status !== 200 || stripos($body, 'error') !== false || stripos($body, 'invalid') !== false) {
            return 'WhatsApp rechazó el mensaje (HTTP ' . $status . '): ' . mb_substr(trim(strip_tags($body)), 0, 160);
        }

        return null;
    }
}
