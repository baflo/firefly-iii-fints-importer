<?php
namespace App\StepFunction;

use App\FinTsFactory;
use App\Step;
use App\TanHandler;
use App\TanChallengeData;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

function Login()
{
    global $request, $session, $twig, $fin_ts, $automate_without_js;

    if ($request->request->has('bank_2fa_device')) {
        $session->set('bank_2fa_device', $request->request->get('bank_2fa_device'));
    }
    $fin_ts = FinTsFactory::create_from_session($session);

    $current_step  = new Step($request->request->get("step", Step::STEP0_SETUP));
    $login_handler = new TanHandler(
        function () {
            global $fin_ts;
            return $fin_ts->login();
        },
        'login-action',
        $session,
        $twig,
        $fin_ts,
        $current_step,
        $request
    );

    if ($login_handler->needs_tan()) {
        if ($automate_without_js && $session->has("bank_2fa_notification_webhook")) {
            $tanChallengeData = $login_handler->pose_and_render_tan_challenge_automated();

            $client = HttpClient::create();// Session-ID aus dem Request extrahieren
            $sessionId = $request->getSession()->getId();
    
            // Webhook-URL des Partners
            $webhookUrl = $session->get("bank_2fa_notification_webhook");
    
            // Payload mit der Session-ID
            $payload = [
                'session_id'            => $sessionId,
                'challenge'             => $tanChallengeData->challenge;
                'device'                => $tanChallengeData->device;
                'challenge_image_src'   => $tanChallengeData->challenge_image_src;
                'is_decoupled_tan_mode' => $tanChallengeData->is_decoupled_tan_mode;
            ];
            
            $response = $client->request('POST', 'webhookUrl', [
                'body' => $payload
            ]);

            // Überprüfen, ob der Request erfolgreich war
            if ($response->getStatusCode() === 200) {
                return Step::STEP3_CHOOSE_ACCOUNT;
            } else {
                return Step::STEP2_LOGIN;
            }
        } else {
            $login_handler->pose_and_render_tan_challenge();
        }
    } else {
        if ($automate_without_js)
        {
            $session->set('persistedFints', $fin_ts->persist());
            return Step::STEP3_CHOOSE_ACCOUNT;
        }
        echo $twig->render(
            'skip-form.twig',
            array(
                'next_step' => Step::STEP3_CHOOSE_ACCOUNT,
                'message' => "The connection to your bank was tested sucessfully."
            )
        );
    }
    $session->set('persistedFints', $fin_ts->persist());
    return Step::DONE;
}