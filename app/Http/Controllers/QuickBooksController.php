<?php

namespace App\Http\Controllers;

use App\Services\QuickBooksService;
use App\Models\QuickBooksToken;
use Illuminate\Http\Request;

class QuickBooksController extends Controller
{
    protected $quickBooksService;

    public function __construct(QuickBooksService $quickBooksService)
    {
        $this->quickBooksService = $quickBooksService;
    }

    // Step 1: Redirect to QuickBooks for OAuth Authorization
    public function redirectToQuickBooks()
    {
        $authUrl = $this->quickBooksService->getAuthUrl();

        return redirect()->to($authUrl);
    }

    // Step 2: Handle the callback from QuickBooks and exchange the code for an access token
    public function handleQuickBooksCallback(Request $request)
    {
        // Extract the authorization code and realm_id from the request query parameters
        $authCode = $request->get('code');
        $realmId = $request->get('realmId');  // Extract realm_id from the query string

        // Ensure that realm_id is numeric (valid QuickBooks company ID)
        if (!is_numeric($realmId)) {
            return response()->json(['error' => 'Invalid realm_id'], 400);
        }

        $tokens = $this->quickBooksService->exchangeCodeForToken($authCode);
 
        // You can store the access token, refresh token, and realm ID in your database or session
        // Store the tokens in the database
        QuickBooksToken::updateOrCreate(
            ['realm_id' => $realmId],
            [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
            ]
        );
        return response()->json($tokens);
    }

    public function refreshQuickBooksAccessToken($realmId)
    {
        // Call the refreshAccessToken method from QuickBooksService
        $newAccessTokenObj = $this->quickBooksService->refreshAccessToken($realmId);

        if ($newAccessTokenObj) {
            // The token was refreshed successfully, return the new access token and refresh token
            return response()->json([
                'access_token' => $newAccessTokenObj->getAccessToken(),
                'refresh_token' => $newAccessTokenObj->getRefreshToken(),
            ]);
        }

        // If no token data is found or the refresh fails, return an error
        return response()->json(['error' => 'Unable to refresh token.'], 400);
    }

}
