<?php

$contacts = json_decode(file_get_contents('../data/contacts.json'), true);

$id = $_GET['id'] ?? '';

foreach ($contacts as $contact) {

    if ($contact['id'] === $id) {

        $vcard = "BEGIN:VCARD\r\n";
        $vcard .= "VERSION:3.0\r\n";
        $vcard .= "FN:{$contact['vorname']} {$contact['nachname']}\r\n";
        $vcard .= "ORG:{$contact['firma']}\r\n";
        $vcard .= "TEL:{$contact['telefon']}\r\n";
        $vcard .= "EMAIL:{$contact['email']}\r\n";
        $vcard .= "END:VCARD\r\n";

        header('Content-Type: text/vcard');
        header('Content-Disposition: attachment; filename="'.$id.'.vcf"');

        echo $vcard;
        exit;
    }
}

http_response_code(404);
