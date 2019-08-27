<?php

namespace SquareBoat\Sneaker;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Mail\Mailer;

// ======
// Use different mailer using solution found in https://stackoverflow.com/questions/26546824/multiple-mail-configurations
class ExceptionMailer extends Mailable implements ShouldQueue {

    use Queueable,
        SerializesModels;

    /**
     * The subject of the message.
     *
     * @var string
     */
    public $subject;

    /**
     * The body of the message.
     *
     * @var string
     */
    public $body;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($subject, $body) {
        $this->subject = $subject;

        $this->body = $body;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build() {
        return $this->view('sneaker::raw')
                ->with('content', $this->body);
    }

    /**
     * Override Mailable functionality to support per-user mail settings
     *
     * @param  \Illuminate\Contracts\Mail\Mailer  $mailer
     * @return void
     */
    public function send(Mailer $mailer) {
        $customMailer = config("sneaker.mailer");
        if ($customMailer != NULL && is_array($customMailer) && isset($customMailer["host"], $customMailer["port"], $customMailer["encryption"], $customMailer["username"], $customMailer["password"])) {
            $host = $customMailer["host"];
            $port = $customMailer["port"];
            $security = $customMailer["encryption"];

            $transport = new \Swift_SmtpTransport($host, $port, $security);
            $transport->setUsername($customMailer["username"]);
            $transport->setPassword($customMailer["password"]);
            
            $mailer->setSwiftMailer(new Swift_Mailer($transport));

            \Container::getInstance()->call([$this, 'build']);
            $mailer->send($this->buildView(), $this->buildViewData(), function ($message) use ($customMailer) {
                $this->buildFrom($message)
                    ->buildRecipients($message)
                    ->buildSubject($message)
                    ->buildAttachments($message)
                    ->runCallbacks($message);
                if (isset($customMailer["from"], $customMailer["from"]["address"])) {
                    $from = $customMailer["from"];
                    $this->setFrom(isset($from["name"], $from["address"]) ? [$from["address"] => $from["name"]] : $from["address"]);
                }
            });
        } else {
            parent::send($mailer);
        }
    }

}
