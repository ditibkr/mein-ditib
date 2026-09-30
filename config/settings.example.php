<?php
return [
    // ── Cloudflare Zero Trust ──
    'cf_logout_url'    => 'https://DEINE_DOMAIN.cloudflareaccess.com/cdn-cgi/access/logout',
    'dev_bypass'       => false,
    'adminer_url'      => 'https://db.DEINE_DOMAIN',
    'admin_user'       => 'admin',
    'admin_password'   => 'AENDERN',

    // ── IMAP ──
    'imap_host'          => 'imap.ionos.de',
    'imap_port'          => 993,
    'imap_user'          => 'info@DEINE_DOMAIN.de',
    'imap_password'      => 'IMAP_PASSWORT',
    'imap_folder'        => 'INBOX',
    'imap_subject_filter'=> 'Zahlungseingang',
    'imap_copy_folder'   => 'spenden-eingang',
    'imap_done_folder'   => 'spenden-archiv',
    'imap_low_folder'    => 'spenden-kleinbetrag',
    'imap_sent_folder'   => 'Gesendete Objekte',
    'imap_copy_stunden'  => 24,
    'imap_seit_datum'    => '',   // z.B. '2026-01-01' oder leer für letzten X Stunden

    // ── SMTP ──
    'smtp_host'       => 'smtp.ionos.de',
    'smtp_port'       => 587,
    'smtp_user'       => 'info@DEINE_DOMAIN.de',
    'smtp_password'   => 'SMTP_PASSWORT',
    'smtp_from_name'  => 'Mein Verein',
    'smtp_from_email' => 'info@DEINE_DOMAIN.de',
    'smtp_mailtext'    => "Sayın {name},\n\nBağışınız için teşekkür ederiz.\n\nSaygılarımızla\nMein Verein",
    'smtp_mailtext_de' => "<p>Sehr geehrte/r {name},</p>\n<p>vielen Dank für Ihre Spende.</p>\n<p>Mit freundlichen Grüßen<br>Mein Verein</p>",

    // ── PayPal REST API (Transaction Search) ──
    // App anlegen unter https://developer.paypal.com → Apps & Credentials
    // Feature "Transaction Search" muss in der App aktiviert sein!
    'paypal_api_aktiv'   => false,       // Import per API im Cron-Job aktivieren
    'paypal_mode'        => 'sandbox',   // 'sandbox' (Test) oder 'live' (Produktion)
    'paypal_client_id'   => '',
    'paypal_secret'      => '',
    'paypal_import_tage' => 3,           // Zeitraum rückwirkend in Tagen (max. 31)
    'paypal_seit_datum'  => '',          // z.B. '2026-07-07' – nie vor diesem Datum importieren (Duplikatschutz beim Umstieg vom Mail-Parsing), leer = nur Zeitraum in Tagen

    // ── Verarbeitung ──
    'mindestbetrag' => 50.0,
    'mail_limit'    => 5,
    'cron_hour'     => 2,
    'cron_minute'   => 0,

    // ── Pfade ──
    'output_dir'     => '/var/www/html/output',
    'template_path'  => '/var/www/html/pdf_templates/vorlage.pdf',
    'beilage_pfad'   => '/var/www/html/pdf_templates/Mitgliedsantrag - Spendeformular.pdf',
    'db_path'        => '/var/www/html/data/spenden.sqlite',
    'log_dir'        => '/var/www/html/logs',

    // ── Testmodus ──
    'testmodus'  => false,
    'test_email' => 'test@beispiel.de',

    // ── Report-Mail nach dem Cron-Job ──
    'report_aktiv' => true,
    'report_email' => '',   // leer = Absenderadresse (smtp_from_email)

    // ── Massenimport alter Bescheinigungen ──
    // PDFs hierher kopieren, dann im Portal "Verzeichnis einlesen"
    'import_dir' => '/var/www/html/import_eingang',

    // ── PDF-Sicherheit ──
    // Guilloche: sinusförmiges Sicherheitsmuster hinter Name/Betrag-Feldern.
    // Im Admin-Panel unter Einstellungen → PDF-Sicherheit aktivierbar.
    'pdf_guilloche_aktiv' => false,
    // Owner-Passwort = Schreibschutz der erzeugten Bescheinigungen (Öffnen bleibt frei).
    'pdf_owner_passwort'  => '',
    // Nur als Rückfallebene: falls eine ALTE PDF beim Import doch ein Öffnen-Passwort hat.
    // Normalerweise nicht nötig – die alten PDFs haben ein leeres User-Passwort.
    'pdf_import_passwort' => '',

    // ── Session ──
    'session_name' => 'spendenportal',
    'dev_user'     => '',   // nur für lokale Entwicklung ohne Cloudflare ZT
];
