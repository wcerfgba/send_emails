<?php
require './vendor/autoload.php';
require './rb.php';

use Symfony\Component\Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

$twig_loader = new \Twig\Loader\FilesystemLoader($_ENV['SEND_EMAILS_TEMPLATES_PATH']);
$twig = new \Twig\Environment($twig_loader);

try {
  $db = new PDO($_ENV['SEND_EMAILS_DATABASE_DSN'], $_ENV['SEND_EMAILS_DATABASE_USERNAME'], $_ENV['SEND_EMAILS_DATABASE_PASSWORD']);
} catch (PDOException $e) {
  error_log($e->getmessage());
  exit(1);
}

$db = null;

try {
  R::setup($_ENV['SEND_EMAILS_DATABASE_DSN'], $_ENV['SEND_EMAILS_DATABASE_USERNAME'], $_ENV['SEND_EMAILS_DATABASE_PASSWORD']);
  R::freeze(true);
} catch (Exception $e) {
  error_log($e->getmessage());
  exit(1);
}

$jobs = R::find($_ENV['SEND_EMAILS_DATABASE_TABLE'], 'sent_at IS NULL AND (error_count IS NULL OR error_count < 3)');
foreach ($jobs as $job) {
  try {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->CharSet = 'utf-8';
    $mail->Debugoutput = 'error_log';
    $mail->SMTPDebug = 0;
    $mail->Host       = $_ENV['SEND_EMAILS_SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SEND_EMAILS_SMTP_USERNAME'];
    $mail->Password   = $_ENV['SEND_EMAILS_SMTP_PASSWORD'];
    $mail->SMTPSecure = 'ssl';
    $mail->Port       = 465;

    $data = $job->export();

    $mail->setFrom($_ENV['SEND_EMAILS_FROM_EMAIL'], $_ENV['SEND_EMAILS_FROM_NAME']);
    $mail->addAddress($data[$_ENV['SEND_EMAILS_DATABASE_RECIPIENT_COLUMN']]);
    if (!empty($_ENV['SEND_EMAILS_REPLYTO_EMAIL'])) {
      $mail->addReplyTo($_ENV['SEND_EMAILS_REPLYTO_EMAIL'], $_ENV['SEND_EMAILS_REPLYTO_NAME']);
    }
    foreach (explode(';', $_ENV['SEND_EMAILS_BCC_EMAILS']) as $bcc) {
      $mail->addBCC($bcc);
    }

    $mail->isHTML(false);
    $mail->Subject = $twig->render($data['template'] . '.subject.twig', $data);
    $mail->Body = $twig->render($data['template'] . '.twig', $data);

    // var_dump($mail); // For debugging ;)

    $mail->send();
    
    $job->sent_at = date(DATE_ATOM);
    R::store($job);

  } catch (Exception $e) {
    error_log($e->getmessage());

    $job->last_error = $e;
    $job->error_count = $job->error_count + 1;
    $job->last_errored_at = date(DATE_ATOM);
    R::store($job);
  }
}
