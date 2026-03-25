<?php

namespace App\Http\Controllers;

use App\Services\CaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    /**
     * Show the contact form.
     */
    public function show()
    {
        return view('contact');
    }

    /**
     * Handle contact form submission.
     */
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'                => 'required|email|max:255',
            'subject'              => 'required|string|max:255',
            'message'              => 'required|string|max:5000',
            'g_recaptcha_response' => 'nullable|string|max:2000',
            'h_captcha_response'   => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        // Sanitize input data
        $sanitizedData = [
            'email'   => filter_var($request->email, FILTER_SANITIZE_EMAIL),
            'subject' => strip_tags(trim($request->subject)),
            'message' => strip_tags(trim($request->message)),
        ];

        // Additional validation after sanitization
        if (!filter_var($sanitizedData['email'], FILTER_VALIDATE_EMAIL)) {
            return back()
                ->withErrors(['email' => 'Invalid email address format.'])
                ->withInput();
        }

        // Verify CAPTCHA (skip in local/testing environments)
        if (!app()->environment(['local', 'testing'])) {
            $captchaService = app(CaptchaService::class);
            $provider = $captchaService->getProvider();
            $captchaResponse = null;

            if ($provider === 'recaptcha' && !empty($request->g_recaptcha_response)) {
                $captchaResponse = $request->g_recaptcha_response;
            } elseif ($provider === 'hcaptcha' && !empty($request->h_captcha_response)) {
                $captchaResponse = $request->h_captcha_response;
            }

            if (!$captchaResponse || !$captchaService->verify($captchaResponse)) {
                return back()
                    ->withErrors(['captcha' => 'CAPTCHA verification failed. Please try again.'])
                    ->withInput();
            }
        }

        // Send email notification
        try {
            $this->sendContactEmail($sanitizedData);

            return back()->with('success', 'Thank you for your message. We will get back to you soon.');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['email' => 'Failed to send message. Please try again later.'])
                ->withInput();
        }
    }

    /**
     * Send contact form email.
     */
    private function sendContactEmail(array $data)
    {
        $adminEmail = config('mail.admin_email', config('mail.from.address'));

        // Rename 'message' to 'messageContent' to avoid conflict with Mail $message object
        $emailData = $data;
        $emailData['messageContent'] = $data['message'];

        Mail::send('emails.contact', $emailData, function ($message) use ($data, $adminEmail) {
            $message->to($adminEmail)
                    ->subject('Contact Form: '.$data['subject'])
                    ->replyTo($data['email']);
        });
    }
}
