<?php

namespace App\Services;

use QuickBooksOnline\API\DataService\DataService;
use App\Models\QuickBooksToken;

class QuickBooksService
{
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;

    public function __construct()
    {
        $this->clientId = env('QUICKBOOKS_CLIENT_ID');
        $this->clientSecret = env('QUICKBOOKS_CLIENT_SECRET');
        $this->redirectUri = env('QUICKBOOKS_REDIRECT_URI');
    }

    // Step 1: Redirect to QuickBooks for OAuth Authorization
    public function getAuthUrl()
    {
        $dataService = DataService::Configure([
            'auth_mode' => 'oauth2',
            'ClientID' => $this->clientId,
            'ClientSecret' => $this->clientSecret,
            'RedirectURI' => $this->redirectUri,
            'scope' => 'com.intuit.quickbooks.accounting',
            'baseUrl' => 'Development',
        ]);

        $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
        return $OAuth2LoginHelper->getAuthorizationCodeURL();
    }

    // Step 2: Exchange the Authorization Code for an Access Token
    public function exchangeCodeForToken($authCode)
    {
        $dataService = DataService::Configure([
            'auth_mode' => 'oauth2',
            'ClientID' => $this->clientId,
            'ClientSecret' => $this->clientSecret,
            'RedirectURI' => $this->redirectUri,
            'scope' => 'com.intuit.quickbooks.accounting',
            'baseUrl' => 'Development',
        ]);

        $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();

        // Exchange the authorization code for an access token and refresh token
        $accessTokenObj = $OAuth2LoginHelper->exchangeAuthorizationCodeForToken($authCode, $this->redirectUri);
    
        // Return tokens
        return [
            'access_token' => $accessTokenObj->getAccessToken(),
            'refresh_token' => $accessTokenObj->getRefreshToken(),
        ];
    }

    // Step 3: Refresh the Access Token using the Refresh Token
    public function refreshAccessToken($realmId)
    {
        // Retrieve the refresh token from the database
        $tokenData = QuickBooksToken::where('realm_id', $realmId)->first();

        if ($tokenData) {
            $dataService = DataService::Configure([
                'auth_mode' => 'oauth2',
                'ClientID' => $this->clientId,
                'ClientSecret' => $this->clientSecret,
                'RedirectURI' => $this->redirectUri,
                'scope' => 'com.intuit.quickbooks.accounting',
                'baseUrl' => 'Development',
            ]);

            $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
            $accessTokenObj = $OAuth2LoginHelper->refreshAccessTokenWithRefreshToken($tokenData->refresh_token);

            // Update the database with the new access token and refresh token
            $tokenData->update([
                'access_token' => $accessTokenObj->getAccessToken(),
                'refresh_token' => $accessTokenObj->getRefreshToken(),
            ]);

            return $accessTokenObj;
        }

        return null;
    }
}
