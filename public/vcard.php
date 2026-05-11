<?php

require_once 'includes/functions.php';
require_installed();

$config = get_config();
$contacts = load_json('contacts.json', []);

$id = $_GET['id'] ?? '';

foreach ($contacts as $contact) {

    if (($contact['id'] ?? '') === $id) {

        $vorname = vcard_escape($contact['vorname'] ?? '');
        $nachname = vcard_escape($contact['nachname'] ?? '');
        $name = trim(($contact['vorname'] ?? '') . ' ' . ($contact['nachname'] ?? ''));
        $nameEscaped = vcard_escape($name);
        $position = vcard_escape($contact['position'] ?? '');
        $company = vcard_escape($config['company_name'] ?? '');
        $url = vcard_escape($config['logo_link'] ?? '');

        $vcard = "BEGIN:VCARD\r\n";
        $vcard .= "VERSION:3.0\r\n";
        $vcard .= "FN;CHARSET=UTF-8:{$nameEscaped}\r\n";
        $vcard .= "N;CHARSET=UTF-8:{$nachname};{$vorname};;;\r\n";

        foreach (data_types($config, true) as $type) {
            $value = data_type_value($contact, $type, $config);

            if ($value === '') {
                continue;
            }

            $field = trim($type['vcard'] ?? '');

            if ($field === '') {
                if (($type['type'] ?? '') === 'email') {
                    $field = 'EMAIL';
                } elseif (($type['type'] ?? '') === 'tel') {
                    $field = 'TEL';
                } elseif (($type['type'] ?? '') === 'url') {
                    $field = 'URL;CHARSET=UTF-8';
                }
            }

            if ($field !== '') {
                $vcard .= $field . ":" . vcard_escape($value) . "\r\n";
            }
        }

        if ($position !== '') {
            $vcard .= "TITLE;CHARSET=UTF-8:{$position}\r\n";
        }

        if ($company !== '') {
            $vcard .= "ORG;CHARSET=UTF-8:{$company}\r\n";
        }

        if ($url !== '') {
            $vcard .= "URL;CHARSET=UTF-8:{$url}\r\n";
        }

        $vcard .= "END:VCARD\r\n";

        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$id.'.vcf"');

        echo $vcard;
        exit;
    }
}

http_response_code(404);
