<?php

require_once 'includes/functions.php';

$config = get_config();
$contacts = load_json('contacts.json', []);

$id = $_GET['id'] ?? '';

foreach ($contacts as $contact) {

    if (($contact['id'] ?? '') === $id) {

        $vcard = "BEGIN:VCARD\r\n";
        $vcard .= "VERSION:3.0\r\n";
        $vcard .= "FN:{$contact['vorname']} {$contact['nachname']}\r\n";
        $vcard .= "ORG:{$config['company_name']}\r\n";

        if (!empty($contact['position'])) {
            $vcard .= "TITLE:{$contact['position']}\r\n";
        }

        $email = contact_email($contact, $config);

        $vcard .= "TEL:{$contact['telefon']}\r\n";
        $vcard .= "EMAIL:{$email}\r\n";
        $vcard .= "END:VCARD\r\n";

        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$id.'.vcf"');

        echo $vcard;
        exit;
    }
}

http_response_code(404);
