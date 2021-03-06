<?php

namespace Hotels4Hope\ArnLaravelApi;

use GuzzleHttp\Client;

class ArnLaravelApi
{
    /**
     * TODO:
     * - Break into different included classes: Members, Locations, Hotels, Deals, Portals, etc.
     * - Add Location::get(), a method to get a normalized location id and name from a string.
     * - Document the params that need to be sent through for each method to work properly.
     * - Check for old dates or invalid data before taking the time to hit the server.
     */

    /**
     * Guzzle HTTP client
     */
    protected $client;

    /**
     * Member API endpoint
     * @var string
     */
    protected $member_uri = 'https://api.travsrv.com/MemberAPI.aspx';

    /**
     * Deals API
     * @var string
     */
    protected $deals_uri = 'https://api.travsrv.com/Content.aspx';

    /**
     * Hotels API
     * @var string
     */
    protected $hotel_uri = 'https://api.travsrv.com/hotel.aspx';

    /**
     * arn portal
     * @var string
     */
    protected $portal_uri = 'https://events.hotelsforhope.com/v6';

    /**
     * Location Search API
     * @var string
     */
    protected $location_search_uri = 'https://api.travsrv.com/widgetapi.aspx';

    /**
     * Hotel Detail API
     * @var string
     */
    protected $property_details_uri = 'https://api.travsrv.com/api/content/findpropertyinfo/';

    /**
     * Admin token
     */
    public $admin_token;

    /**
     * Member token
     */
    public $member_token;

    /**
     * The query we're sending to the API
     * @var array
     */
    public $query = [];

    /**
     * Stack of requests and responses
     * @var array
     */
    public $stack = [];

    /**
     * Construtor
     */
    public function __construct()
    {
        if (! $this->apiCredentialsExist()) {
            throw new \Exception('ARN API credentials do not exist in .env file');
        }
        $this->client = new Client(['http_errors' => false, 'headers' => ['Accept-version' => config('arnlaravelapi.arn_api_version')]]);

        $this->getAdminToken();
    }

    /**
     * Checks if the API credentials are in the .env file
     * @return boolean
     */
    private function apiCredentialsExist()
    {
        if (empty(config('arnlaravelapi.arn_api_username'))
            || empty(config('arnlaravelapi.arn_api_password'))
            || empty(config('arnlaravelapi.arn_api_site_admin_username'))
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get the current member's SSO URL to the portal
     * @return string
     */
    public function getPortalUri()
    {
        return $this->constructPortalUri();
    }

    /**
     * Construct and return the current member's SSO URL to the portal
     * @return string
     */
    public function constructPortalUri()
    {
        return $this->portal_uri . '?memberToken=' . urlencode($this->member_token);
    }

    /**
     * Construct and upsert a member
     * @param  array  $params [description]
     * @return [type]         [description]
     */
    public function constructAndUpsertMember(array $params = [])
    {
        $memberData = $this->constructMemberObject($params);

        // FIXME: This bit is required since ARN has a bug in it where
        // AdditionalInfo isn't saved when created, only when updated
        $decoded = json_decode($memberData);
        if (! isset($decoded->AdditionalInfo) || empty($decoded->AdditionalInfo)) {
            $memberData = $this->constructMemberObject($params);
        }

        return $this->upsertMember(['memberData' => $memberData]);
    }

    /**
     * Delete/Deactive a member
     * @param  array  $params [description]
     * @return [type]         [description]
     */
    public function deleteMember(array $params = [])
    {
        $params['is_active'] = false;
        $memberData = $this->constructMemberObject($params);

        return $this->upsertMember(['memberData' => $memberData]);
    }

    /**
     * Create a memberData object and then json_encode it
     * @param  array  $params
     * @return string
     */
    public function constructMemberObject(array $params = [])
    {
        $full_name = $params['first_name'] ?? '';
        $full_name .= ' ' . $params['last_name'] ?? '';

        $user = new \stdClass();
        $user->ReferralId = $params['id'] ?? '';
        $user->FirstName = $params['first_name'] ?? '';
        $user->LastName = $params['last_name'] ?? '';
        $user->Email = $params['email'] ?? '';

        // Delete/Deactive or Reactivate a member if 'is_active' passed in (bool)
        if (isset($params['is_active'])) {
            if (empty($params['is_active'])) {
                $user->DeleteMember = true;
            } else {
                $user->ReactivateMember = true;
            }
        }

        $memberData = new \stdClass();
        $memberData->Names = [$user];
        $additionalInfoData = new \stdClass();
        $additionalInfoData->partner = $params['partner'] ?? '';
        $additionalInfoData->id = $params['id'] ?? '';
        $additionalInfoData->name = $full_name;
        $additionalInfoData->email = $params['email'] ?? '';
        $memberData->AdditionalInfo = json_encode($additionalInfoData);

        if (isset($params['points'])) {
            $memberData->Points = $params['points'];
        }

        return json_encode($memberData);
    }

    /**
     * Gets an Admin Token
     * @param  array  $params
     * @return array
     */
    public function getAdminToken(array $params = [])
    {
        $this->query = $this->mergeSiteAdminCredentials($params);

        $response = $this->client->request('GET', $this->member_uri, ['query' => $this->query]);

        $json = json_decode((string) $response->getBody(), true);

        if (isset($json['CurrentToken'])) {
            $this->admin_token = urldecode($json['CurrentToken']);
        }

        $this->stack[] = [
            'function' => __FUNCTION__,
            'params' => $params,
            'code' => $response->getStatusCode(),
            'body' => $json,
            'response' => $response,
        ];

        return end($this->stack);
    }

    /**
     * Creates a Member
     * @param  array  $params
     * @return array
     */
    public function createMember(array $params = [])
    {
        extract($this->upsertMember($params), __FUNCTION__);

        return end($this->stack);
    }

    /**
     * Updates a Member
     * @param  array  $params
     * @return array
     */
    public function updateMember(array $params = [])
    {
        extract($this->upsertMember($params), __FUNCTION__);

        return end($this->stack);
    }

    /**
     * Update or insert/create the member data
     * @param  array  $params
     * @param  mixed  $function Name of function calling this one, or null by default
     * @return array
     */
    private function upsertMember(array $params = [], $function = null)
    {
        $this->query = $this->mergeSiteAdminToken($params);

        $response = $this->client->request('POST', $this->member_uri, [
            'form_params' => $this->query,
        ]);

        $json = json_decode((string) $response->getBody(), true);

        if (isset($json['CurrentToken'])) {
            $this->member_token = urldecode($json['CurrentToken']);
        }

        $this->stack[] = [
            'function' => (! empty($function)) ? $function : __FUNCTION__,
            'params' => $params,
            'code' => $response->getStatusCode(),
            'body' => $json,
            'response' => $response,
        ];

        return end($this->stack);
    }

    /**
     * Merges the site admin credentials into the request
     * @param  array  $query
     * @return array
     */
    private function mergeSiteAdminCredentials(array $query = [], $withToken = true)
    {
        $credentials = [
            'username' => config('arnlaravelapi.arn_api_username'),
            'password' => config('arnlaravelapi.arn_api_password'),
            'siteid' => config('arnlaravelapi.arn_api_site_id'),
        ];

        if ($withToken) {
            $credentials['token'] = 'ARNUSER-' . config('arnlaravelapi.arn_api_site_admin_username');
        }

        return array_merge($query, $credentials);
    }

    /**
     * Merges the site admin token into the request
     * @param  array  $query
     * @return array
     */
    private function mergeSiteAdminToken(array $query = [])
    {
        $credentials = [
            'token' => $this->admin_token,
            'siteid' => config('arnlaravelapi.arn_api_site_id'),
        ];

        return array_merge($query, $credentials);
    }

    /**
     * Get the locations with the best deals
     * @return array
     */
    public function getDealsLocations(array $params = [])
    {
        $params['type'] = 'findfeaturedlocationdeals';

        $response = $this->client->request('GET', $this->deals_uri, [
            'query' => $params,
        ]);

        $json = json_decode((string) $response->getBody(), true);

        $this->stack[] = [
            'function' => (! empty($function)) ? $function : __FUNCTION__,
            'params' => $params,
            'code' => $response->getStatusCode(),
            'body' => $json,
            'response' => $response,
        ];

        return end($this->stack);
    }

    /**
     * Get the locations with the best deals
     * @return array
     */
    public function getDealsHotels($location_id = null, array $params = [])
    {
        $params['type'] = 'findfeaturedhoteldeals';

        if (! empty($location_id)) {
            $params['locationid'] = $location_id;
        }

        $response = $this->client->request('GET', $this->deals_uri, [
            'query' => $params,
        ]);

        $json = json_decode((string) $response->getBody(), true);

        $this->stack[] = [
            'function' => (! empty($function)) ? $function : __FUNCTION__,
            'params' => $params,
            'code' => $response->getStatusCode(),
            'body' => $json,
            'response' => $response,
        ];

        return end($this->stack);
    }

    public function getLocationId(string $city, array $params = [])
    {
        $params['type'] = 'cities';
        $params['name'] = $city;

        $response = $this->client->request('GET', $this->location_search_uri, [
            'query' => $params,
        ]);

        $json = json_decode((string) $response->getBody(), true);

        $this->stack[] = [
            'function' => (! empty($function)) ? $function : __FUNCTION__,
            'params' => $params,
            'code' => $response->getStatusCode(),
            'body' => $json,
            'response' => $response,
        ];

        return end($this->stack);
    }

    /**
     * Get availability for a particular location and dates
     * @return array
     */
    public function getAvailability(array $params = [])
    {
        $params = $this->mergeSiteAdminCredentials($params, false);

        try {
            $response = $this->client->request('GET', $this->hotel_uri, [
                'query' => $params,
            ]);
        } catch (Exception $e) {
            // Example: `416 Requested Range Not Satisfiable` response:
            // {"ArnResponse":{"Error":{"Type":"NoHotelsFoundException","Message":"No Hotels Found to satisfy your request."}}}
        }

        $json = json_decode((string) $response->getBody(), true);

        $this->stack[] = [
            'function' => (! empty($function)) ? $function : __FUNCTION__,
            'params' => $params,
            'code' => $response->getStatusCode(),
            'body' => $json,
            'response' => $response,
        ];

        return end($this->stack);
    }

    /**
     * Returns hotel details
     *
     * @param   int    $property_id
     * @param   array  $params
     *
     * @return  array
     */
    public function getHotelDetails(int $property_id, array $params = [])
    {
        $params = $this->mergeSiteAdminCredentials($params, false);
        $params['propertyid'] = $property_id;

        try {
            $response = $this->client->request('GET', $this->property_details_uri, [
                'query' => $params,
            ]);
        } catch (Exception $e) {
        }

        $json = json_decode((string) $response->getBody(), true);

        if (! empty($json['Images']) && ! empty($json['Images'][0]) && ! empty($json['Images'][0]['ImagePath'])) {
            $json['FeaturedImage'] = $json['Images'][0]['ImagePath'];
            $json['HighResolutionFeaturedImage'] = $this->getHighResolutionFeaturedImage($json['Images'][0]['ImagePath']);
        }

        $this->stack[] = [
            'function' => (! empty($function)) ? $function : __FUNCTION__,
            'params' => $params,
            'code' => $response->getStatusCode(),
            'body' => $json,
            'response' => $response,
        ];

        return end($this->stack);
    }

    /**
     * Replaces low resolution version of the featured image with higher resolution version
     *
     * @param   string  $image_url
     *
     * @return  string
     */
    private function getHighResolutionFeaturedImage(string $image_url)
    {
        return str_replace('_300.jpg', '_804480.jpg', $image_url);
    }
}
