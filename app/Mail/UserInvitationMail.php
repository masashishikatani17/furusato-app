<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Invitation $invitation;

    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
    }

    public function build()
    {
        // 招待URLのルートが未実装でもメール本文は生成できるようにしておく
        $acceptUrl = route('invitations.accept', ['token' => $this->invitation->token]);

        return $this->subject('ユーザー招待のご案内')
            ->view('emails.user_invitation')
            ->with([
                'invitation' => $this->invitation,
                'acceptUrl'  => $acceptUrl,
            ]);
    }
}
