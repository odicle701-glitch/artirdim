<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PageController extends Controller
{
    public function corporate()
    {
        return view('corporta');
    }

    public function privacy_policy()
    {
        return view('privay-policy');
    }

    public function contact()
    {
        return view('contact');
    }

    public function contactSend(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'subject' => ['required', 'in:genel,teknik,odeme,sikayet,isbirligi'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'name.required' => 'Ad soyad alanı zorunludur.',
            'name.min' => 'Ad soyad en az 2 karakter olmalıdır.',
            'email.required' => 'E-posta alanı zorunludur.',
            'email.email' => 'Geçerli bir e-posta adresi giriniz.',
            'subject.required' => 'Lütfen bir konu seçiniz.',
            'subject.in' => 'Geçersiz konu seçimi.',
            'message.required' => 'Mesaj alanı zorunludur.',
            'message.min' => 'Mesajınız en az 10 karakter olmalıdır.',
            'message.max' => 'Mesajınız en fazla 2000 karakter olabilir.',
        ]);

        $subjectLabels = [
            'genel' => 'Genel Bilgi',
            'teknik' => 'Teknik Destek',
            'odeme' => 'Ödeme / Fatura',
            'sikayet' => 'Şikayet',
            'isbirligi' => 'İş Birliği',
        ];

        try {
            Mail::send('emails.contact', [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'subject' => $subjectLabels[$validated['subject']],
                'userMessage' => $validated['message'],   // ← message → userMessage
            ], function ($mail) use ($validated, $subjectLabels) {
                $mail->to(config('mail.contact_address', 'destek@artirdim.com'))
                    ->replyTo($validated['email'], $validated['name'])
                    ->subject('[İletişim] '.$subjectLabels[$validated['subject']]);
            });
        } catch (\Exception $e) {
            Log::error('ContactSend mail hatası: '.$e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Mesajınız gönderilemedi. Lütfen daha sonra tekrar deneyin.');
        }

        return back()->with('contact_success', true);
    }
}
