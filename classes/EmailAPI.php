<?php
require_once "config.php";
class EmailAPI {
    private $mailbox;

    // Constructeur pour initialiser la connexion
    public function __construct() {
        $this->connect_mailbox();
    }

    // Méthode pour se connecter à la boîte de reception
    private function connect_mailbox() {
        $this->mailbox = imap_open(HOSTNAME, USERNAME, PASSWORD);

        if (!$this->mailbox) {
            die("Connection error: " . imap_last_error());
        }
    }

    // Méthode pour verifier si la connexion est ouverte et la rouvrir si nécessaire
    private function ensure_mailbox_open() {
        if (!$this->mailbox || imap_errors() !== []) {
            $this->connect_mailbox();
        }
    }

    // Méthode pour fermer la connexion à la boîte de réception
    private function close_mailbox() {
        if ($this->mailbox) {
            imap_close($this->mailbox);
            $this->mailbox = null;
        }
    }

    // Méthode pour décoder le contenu en fonction de l'encodage
    private function decode_content($message, $encoding) {
        return match ($encoding) {
            3 => base64_decode($message), // Encodage BASE64
            4 => quoted_printable_decode($message), // Encodage QUOTED-PRINTABLE
            default => $message
        };
    }

    // Méthode pour décoder les en-têtes MIME
    private function decode_mime_header($header) {
        return mb_decode_mimeheader($header);
    }

    // Méthode pour nettoyer le contenu HTML
    private function clean_html_content($html) {
        return preg_replace("/<(style|script|head|meta|link|html|body)[^>]*>.*?<\/\\1>/si", "", $html);
    }

    // Méthode pour nettoyer le contenu brut
    private function clean_plain_content($text) {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, "UTF-8");
        $text = mb_convert_encoding($text, "UTF-8", "auto");
        return preg_replace("/[\r\n]+/", "\n", $text);
    }

    // Méthode pour générer un nombre aléatoire entre 1000 et 9999
    private function generate_random_number() {
        return rand(1000, 9999);
    }

    // Méthode pour obtenir les détails d'un email spécifique
    public function get_email_details($email_number) {
        $this->ensure_mailbox_open();

        // Verifier si le numero de message existe
        $overview = imap_fetch_overview($this->mailbox, $email_number, 0);
        if (!$overview) {
            $this->close_mailbox();
            throw new Exception("Email with ID $email_number not found.");
        }

        $structure = imap_fetchstructure($this->mailbox, $email_number);
        $headers = imap_headerinfo($this->mailbox, $email_number);

        $plain_text = $html_text = "";
        $attachments = [];
        $all_recipients = array_merge(
            isset($headers->to) ? $headers->to : [],
            isset($headers->cc) ? $headers->cc : [],
            isset($headers->bcc) ? $headers->bcc : []
        );

        $all_recipients = array_map(function($recipient) {
            return is_object($recipient) ? $recipient->mailbox . "@" . $recipient->host : $recipient;
        }, $all_recipients);

        if (!empty($structure->parts)) {
            foreach ($structure->parts as $part_index => $part) {
                if ($part->subtype === "PLAIN") {
                    $plain_text .= $this->decode_content(imap_fetchbody($this->mailbox, $email_number, $part_index + 1), $part->encoding);
                } elseif ($part->subtype === "HTML") {
                    $html_text .= $this->decode_content(imap_fetchbody($this->mailbox, $email_number, $part_index + 1), $part->encoding);
                } elseif (isset($part->disposition) && strtolower($part->disposition) === "attachment") {
                    $filename = $part->dparameters[0]->value ?? "unknown_file";
                    $attachment_data = $this->decode_content(imap_fetchbody($this->mailbox, $email_number, $part_index + 1), $part->encoding);
                    $date = date("Ymd_His");
                    $random_number = $this->generate_random_number();
                    $attachment_path = "attachments/" . $date . "_" . $random_number . "_" . $filename;
                    $attachments[] = [
                        "filename" => $filename,
                        "path" => "http://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["PHP_SELF"]) . "/" . $attachment_path
                    ];
                } elseif ($part->type === 1) {
                    foreach ($part->parts as $sub_part_index => $sub_part) {
                        if ($sub_part->subtype === "PLAIN") {
                            $plain_text .= $this->decode_content(imap_fetchbody($this->mailbox, $email_number, $part_index + 1 . "." . ($sub_part_index + 1)), $sub_part->encoding);
                        } elseif ($sub_part->subtype === "HTML") {
                            $html_text .= $this->decode_content(imap_fetchbody($this->mailbox, $email_number, $part_index + 1 . "." . ($sub_part_index + 1)), $sub_part->encoding);
                        } elseif (isset($sub_part->disposition) && strtolower($sub_part->disposition) === "attachment") {
                            $filename = $sub_part->dparameters[0]->value ?? "unknown_file";
                            $attachment_data = $this->decode_content(imap_fetchbody($this->mailbox, $email_number, $part_index + 1 . "." . ($sub_part_index + 1)), $sub_part->encoding);
                            $date = date("Ymd_His");
                            $random_number = $this->generate_random_number();
                            $attachment_path = "attachments/" . $date . "_" . $random_number . "_" . $filename;
                            $attachments[] = [
                                "filename" => $filename,
                                "path" => "http://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["PHP_SELF"]) . "/" . $attachment_path
                            ];
                        }
                    }
                }
            }
        } else {
            $plain_text = $this->decode_content(imap_body($this->mailbox, $email_number, 0), $structure->encoding);
        }

        $html_text = $this->clean_html_content($html_text);
        $plain_text = $this->clean_plain_content($plain_text);

        $email_details = [
            "email_id" => $email_number,
            "subject" => $this->decode_mime_header($overview[0]->subject ?? ""),
            "from" => $this->decode_mime_header($overview[0]->from ?? ""),
            "date" => $overview[0]->date ?? "",
            "readen" => $overview[0]->seen ? "yes" : "no",
            "plain_content" => $plain_text,
            "html_content" => $html_text,
            "attachments" => $attachments,
            "recipients" => array_map([$this, "decode_mime_header"], $all_recipients)
        ];

        $this->close_mailbox();
        return $email_details;
    }

    // Méthode pour obtenir tous les emails non lus
    public function get_unread_emails() {
        $this->ensure_mailbox_open();
        $emails = imap_search($this->mailbox, "UNSEEN");

        if (!$emails) {
            $this->close_mailbox();
            return "You have no unread emails";
        }

        $unread_emails = [];
        foreach ($emails as $email_number) {
            $unread_emails[] = $this->get_email_details($email_number);
        }

        $this->close_mailbox();
        return $unread_emails;
    }

    // Méthode pour installer les pièces jointes d'un email spécifique
    public function install_attachments($email_number) {
        $this->ensure_mailbox_open();

        // Verifier si le numéro de message existe
        $structure = imap_fetchstructure($this->mailbox, $email_number);
        if (!$structure) {
            $this->close_mailbox();
            throw new Exception("Email with ID $email_number not found.");
        }

        if (!is_dir("attachments")) {
            mkdir("attachments", 0755, true);
        }

        $attachments = [];
        if (!empty($structure->parts)) {
            foreach ($structure->parts as $part_index => $part) {
                if (isset($part->disposition) && strtolower($part->disposition) === "attachment") {
                    $filename = $part->dparameters[0]->value ?? "unknown_file";
                    $attachment_data = $this->decode_content(imap_fetchbody($this->mailbox, $email_number, $part_index + 1), $part->encoding);
                    $date = date("Ymd_His");
                    $random_number = $this->generate_random_number();
                    $attachment_path = "attachments/" . $date . "_" . $random_number . "_" . $filename;

                    // Verifier si le fichier existe déja
                    if (!file_exists($attachment_path)) {
                        file_put_contents($attachment_path, $attachment_data);
                    }

                    $attachments[] = [
                        "filename" => $filename,
                        "path" => "http://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["PHP_SELF"]) . "/" . $attachment_path
                    ];
                }
            }
        }

        $this->close_mailbox();
        return [
            "message" => "The attachments of the email [$email_number] have been installed successfully.",
            "attachments" => $attachments
        ];
    }

    // Méthode pour obtenir tous les emails
    public function get_all_emails() {
        $this->ensure_mailbox_open();
        $emails = [];
        $num_messages = imap_num_msg($this->mailbox);

        for ($i = $num_messages; $i > 0; $i--) {
            $overview = imap_fetch_overview($this->mailbox, $i, 0);
            $structure = imap_fetchstructure($this->mailbox, $i);
            $headers = imap_headerinfo($this->mailbox, $i);

            // Verifier si le mail contient des piéces jointes
            $has_attachments = false;
            if (!empty($structure->parts)) {
                foreach ($structure->parts as $part) {
                    if (isset($part->disposition) && strtolower($part->disposition) === "attachment") {
                        $has_attachments = true;
                        break;
                    }
                }
            }

            // Recupere les destinataires
            $to = isset($headers->to) ? $headers->to : [];
            $cc = isset($headers->cc) ? $headers->cc : [];
            $bcc = isset($headers->bcc) ? $headers->bcc : [];
            $all_recipients = array_merge($to, $cc, $bcc);

            $all_recipients = array_map(function($recipient) {
                return is_object($recipient) ? $recipient->mailbox . "@" . $recipient->host : $recipient;
            }, $all_recipients);

            $emails[] = [
                "email_number" => $i,
                "subject" => isset($overview[0]->subject) ? $overview[0]->subject : "No subject",
                "from" => isset($overview[0]->from) ? $overview[0]->from : "Unknown",
                "date" => isset($overview[0]->date) ? $overview[0]->date : "Unknown date",
                "readen" => isset($overview[0]->seen) ? "yes" : "no",
                "hasAttachments" => $has_attachments,
                "recipients" => array_map([$this, "decode_mime_header"], $all_recipients)
            ];
        }

        $this->close_mailbox();
        return $emails;
    }
}