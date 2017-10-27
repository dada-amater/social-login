<?php

namespace VencaX;

use Nette;
use Facebook;
use Exception;

/**
 * Login with Twitter API
 * Class TwitterLogin
 * @package VencaX
 */
class TwitterLogin extends BaseLogin
{

    const SOCIAL_NAME = "twitter";

    /** @var \TwitterOAuth */
    private $twitterOAuth;

    /** @var array Request token */
    private $requestToken;

    /** @var string scope */
    private $scope = "";

    /** @var Nette\Http\Session session */
    private $session;

    /**
     * Twitter
     * @param $params array - data from config.neon
     * @param $cookieName String cookie name
     * @param Nette\Http\Session $session
     * @param Nette\Http\IResponse $httpResponse
     * @param Nette\Http\IRequest $httpRequest
     */
    public function __construct( $params, $cookieName, Nette\Http\Session $session, Nette\Http\IResponse $httpResponse, Nette\Http\IRequest $httpRequest )
    {
        $this->params = $params;
        $this->cookieName = $cookieName;
        $this->session = $session;
        $this->httpResponse = $httpResponse;
        $this->httpRequest = $httpRequest;
    }

    /**
     * Set scope
     * @param $scope
     */
    public function setScope( $scope )
    {
        $this->scope = $scope;
    }

    /**
     * Get URL for login
     * @param string $callbackURL
     * @return string URL login
     */
    public function getLoginUrl()
    {
        $this->twitterOAuth = new \TwitterOAuth( $this->params["consumerKey"], $this->params["consumerSecret"] );
        $this->requestToken = $this->twitterOAuth->getRequestToken( $this->params["callbackURL"] );

        $sessionSection = $this->session->getSection( "twitter" );
        $sessionSection->oauth_token = $token = $this->requestToken['oauth_token'];
        $sessionSection->oauth_token_secret = $this->requestToken['oauth_token_secret'];

        /*
        // If last connection failed don't display authorization link.
        switch ( $this->twitterOAuth->http_code ) {
            case 200:
                // Build authorize URL and redirect user to Twitter.
                $url = $this->twitterOAuth->getAuthorizeURL( $this->requestToken['oauth_token'] );
                $this->redirectUrl( $url );
                break;
            default:
                // Show notification if something went wrong.
                $this->flashMessage( 'Could not connect to Twitter.', "error" );
                $this->redirect( "Sign:login" );
        }*/

        $loginUrl = $this->twitterOAuth->getAuthorizeURL( $this->requestToken ); // Use Sign in with Twitter
        return $loginUrl;
    }

    /**
     * Return info about login user
     * @param $oauthToken
     * @param $oauthVerifier
     * @return \API|mixed
     * @throws Exception
     */
    public function getMe( $oauthToken, $oauthVerifier )
    {
        $sessionSection = $this->session->getSection( "twitter" );

        if ( ( $oauthToken != "" ) && ( $sessionSection->oauth_token !== $oauthToken ) ) {
            //$sessionSection->oauth_status = 'oldtoken';
            throw new Exception( "Twitter token is old. Try again login" );
        }

        //Create TwitteroAuth object with app key/secret and token key/secret from default phase
        $this->twitterOAuth = new \TwitterOAuth( $this->params["consumerKey"], $this->params["consumerSecret"], $sessionSection->oauth_token, $sessionSection->oauth_token_secret );

        //Request access tokens from twitter
        $access_token = $this->twitterOAuth->getAccessToken( $oauthVerifier );

        //Save the access tokens
        $sessionSection->access_token = $access_token;

        // Remove no longer needed request tokens
        unset( $sessionSection->oauth_token );
        unset( $sessionSection->oauth_token_secret );

        if (200 != $this->twitterOAuth->http_code) {
            //Save HTTP status for error dialog on connnect page.
            throw new Exception("Twitter login. Something is wrong");
        }

        //The user has been verified
        //$sessionSection->status = 'verified';

        $user_info = $this->twitterOAuth->get( 'account/verify_credentials' );

        $this->setSocialLoginCookie( self::SOCIAL_NAME );

        return $user_info;

    }

    /**
     * Is user last login with this service<
     * @return bool
     */
    public function isThisServiceLastLogin()
    {
        if( $this->getSocialLoginCookie() == self::SOCIAL_NAME )
        {
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }

}