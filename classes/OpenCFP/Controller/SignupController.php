<?php
namespace OpenCFP\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Cartalyst\Sentry\Users\UserExistsException;
use OpenCFP\Form\SignupForm;
use OpenCFP\Model\Speaker;
use Intervention\Image\Image;

class SignupController
{
    public function getFlash(Application $app)
    {
        $flash = $app['session']->get('flash');
        $this->clearFlash($app);
        return $flash;
    }

    public function clearFlash(Application $app)
    {
        $app['session']->set('flash', null);
    }

    public function indexAction(Request $req, Application $app)
    {
        // Reset our user to make sure nothing weird happens
        if ($app['sentry']->check()) {
            $app['sentry']->logout();
        }

        $template = $app['twig']->loadTemplate('user/create.twig');
        $form_data = array();
        $form_data['formAction'] = '/signup';
        $form_data['buttonInfo'] = 'Create my speaker profile';

        return $template->render($form_data);
    }


    public function processAction(Request $req, Application $app)
    {
        $template_name = 'create_user.twig';
        $form_data = array(
            'first_name' => $req->get('first_name'),
            'last_name' => $req->get('last_name'),
            'company' => $req->get('company'),
            'twitter' => $req->get('twitter'),
            'email' => $req->get('email'),
            'password' => $req->get('password'),
            'password2' => $req->get('password2'),
            'airport' => $req->get('airport')
        );
        $form_data['speaker_info'] = $req->get('speaker_info') ?: null;
        $form_data['speaker_bio'] = $req->get('speaker_bio') ?: null;

        $form = new SignupForm($form_data, $app['purifier']);
        $form->sanitize();

        if ($form->validateAll()) {
            $sanitized_data = $form->getCleanData();

            // Create account using Sentry
            $user_data = array(
                'first_name' => $sanitized_data['first_name'],
                'last_name' => $sanitized_data['last_name'],
                'company' => $sanitized_data['company'],
                'twitter' => $sanitized_data['twitter'],
                'email' => $sanitized_data['email'],
                'password' => $sanitized_data['password'],
                'airport' => $sanitized_data['airport'],
                'activated' => 1
            );

            // Remove leading @ for twitter
            if ($sanitized_data['twitter'][0] === "@") {
                $sanitized_data['twitter'] = substr($sanitized_data['twitter'], 1);
            }

            try {
                $user = $app['sentry']->getUserProvider()->create($user_data);

                // Add them to the proper group
                $adminGroup = $app['sentry']->getGroupProvider()->findByName('Speakers');
                $user->addGroup($adminGroup);

                // Create a Speaker record
                $speaker = new Speaker($app['db']);
                $response = $speaker->create(array(
                    'user_id' => $user->getId(),
                    'info' => $sanitized_data['speaker_info'],
                    'bio' => $sanitized_data['speaker_bio']
                ));

                // Set Success Flash Message
                $app['session']->set('flash', array(
                    'type' => 'success',
                    'short' => 'Success',
                    'ext' => "You've successfully created your account!",
                ));

                return $app->redirect($app['url'] . '/login');
            } catch (UserExistsException $e) {
                $errorMessage = 'A user already exists with that email address';
            }
        } else {
            $errorMessage = implode("<br>", $form->getErrorMessages());
        }

        // Set Success Flash Message
        $app['session']->set('flash', array(
            'type' => 'error',
            'short' => 'Error',
            'ext' => $errorMessage,
        ));

        $template = $app['twig']->loadTemplate('user/create.twig');
        $form_data['formAction'] = '/signup';
        $form_data['buttonInfo'] = 'Create my speaker profile';
        $form_data['flash'] = $this->getFlash($app);

        return $template->render($form_data);
    }
}
