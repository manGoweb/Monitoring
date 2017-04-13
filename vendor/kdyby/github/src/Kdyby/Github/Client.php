<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Github;

use Kdyby;
use Nette;
use Nette\Utils\ArrayHash;



/**
 * Github api client that serves for communication
 * and handles authorization on the api level.
 *
 * @author Filip Procházka <filip@prochazka.su>
 */
class Client extends Nette\Object
{

	const AUTH_URL_TOKEN = 'url_token';
	const AUTH_URL_CLIENT_ID = 'url_client_id';
	const AUTH_HTTP_PASSWORD = 'http_password';

	/**
	 * @var Api\CurlClient
	 */
	private $httpClient;

	/**
	 * @var Configuration
	 */
	private $config;

	/**
	 * @var \Nette\Http\IRequest
	 */
	private $httpRequest;

	/**
	 * @var SessionStorage
	 */
	private $session;

	/**
	 * The ID of the Github user, or 0 if the user is logged out.
	 * @var integer
	 */
	protected $user;

	/**
	 * The OAuth access token received in exchange for a valid authorization code.
	 * null means the access token has yet to be determined.
	 * @var string
	 */
	protected $accessToken;

	/**
	 * Indicates the authorization method for next api call.
	 * @var string
	 */
	protected $authorizeBy;



	/**
	 * @param Configuration $config
	 * @param Nette\Http\IRequest $httpRequest
	 * @param SessionStorage $session
	 * @param Api\CurlClient $httpClient
	 */
	public function __construct(Configuration $config, Nette\Http\IRequest $httpRequest, SessionStorage $session, HttpClient $httpClient)
	{
		$this->config = $config;
		$this->httpRequest = $httpRequest;
		$this->session = $session;
		$this->httpClient = $httpClient;
	}



	/**
	 * @return Configuration
	 */
	public function getConfig()
	{
		return $this->config;
	}



	/**
	 * @return Nette\Http\UrlScript
	 */
	public function getCurrentUrl()
	{
		return clone $this->httpRequest->getUrl();
	}



	/**
	 * @return SessionStorage
	 */
	public function getSession()
	{
		return $this->session;
	}



	/**
	 * Get the UID of the connected user, or 0 if the Github user is not connected.
	 *
	 * @return string the UID if available.
	 */
	public function getUser()
	{
		if ($this->user === NULL) {
			$this->user = $this->getUserFromAvailableData();
		}

		return $this->user;
	}



	/**
	 * @param int|string $profileId
	 * @return Profile
	 */
	public function getProfile($profileId = NULL)
	{
		return new Profile($this, $profileId);
	}



	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 * @throws ApiException
	 * @return ArrayHash|string|Paginator|ArrayHash[]
	 */
	public function get($path, array $params = array(), array $headers = array())
	{
		return $this->api($path, Api\Request::GET, $params, array(), $headers);
	}



	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 * @throws ApiException
	 * @return ArrayHash|string|Paginator|ArrayHash[]
	 */
	public function head($path, array $params = array(), array $headers = array())
	{
		return $this->api($path, Api\Request::HEAD, $params, array(), $headers);
	}



	/**
	 * @param string $path
	 * @param array $params
	 * @param array|string $post
	 * @param array $headers
	 * @throws ApiException
	 * @return ArrayHash|string|Paginator|ArrayHash[]
	 */
	public function post($path, array $params = array(), $post = array(), array $headers = array())
	{
		return $this->api($path, Api\Request::POST, $params, $post, $headers);
	}



	/**
	 * @param string $path
	 * @param array $params
	 * @param array|string $post
	 * @param array $headers
	 * @throws ApiException
	 * @return ArrayHash|string|Paginator|ArrayHash[]
	 */
	public function patch($path, array $params = array(), $post = array(), array $headers = array())
	{
		return $this->api($path, Api\Request::PATCH, $params, $post, $headers);
	}



	/**
	 * @param string $path
	 * @param array $params
	 * @param array|string $post
	 * @param array $headers
	 * @throws ApiException
	 * @return ArrayHash|string|Paginator|ArrayHash[]
	 */
	public function put($path, array $params = array(), $post = array(), array $headers = array())
	{
		return $this->api($path, Api\Request::PUT, $params, $post, $headers);
	}



	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 * @throws ApiException
	 * @return ArrayHash|string|Paginator|ArrayHash[]
	 */
	public function delete($path, array $params = array(), array $headers = array())
	{
		return $this->api($path, Api\Request::DELETE, $params, array(), $headers);
	}



	/**
	 * Simply pass anything starting with a slash and it will call the Api, for example
	 * <code>
	 * $details = $github->api('/user');
	 * </code>
	 *
	 * @param string $path
	 * @param string $method The argument is optional
	 * @param array $params Query parameters
	 * @param array|string $post Post request parameters or body to send
	 * @param array $headers Http request headers
	 * @throws ApiException
	 * @return ArrayHash|string|Paginator|ArrayHash[]
	 */
	public function api($path, $method = Api\Request::GET, array $params = array(), $post = array(), array $headers = array())
	{
		if (is_array($method)) {
			$headers = $post;
			$post = $params;
			$params = $method;
			$method = Api\Request::GET;
		}

		list($params, $headers) = $this->authorizeRequest($params, $headers);
		$response = $this->httpClient->makeRequest(
			new Api\Request($this->buildRequestUrl($path, $params), $method, $post, $headers)
		);

		if ($response->isPaginated()) {
			return new Paginator($this, $response);
		}

		return $response->isJson() ? ArrayHash::from($response->toArray()) : $response->getContent();
	}



	protected function authorizeRequest($params, $headers)
	{
		if (isset($this->authorizeBy[self::AUTH_URL_CLIENT_ID])) {
			$params['client_id'] = $this->config->appId;
			$params['client_secret'] = $this->config->appSecret;

		} elseif (isset($this->authorizeBy[self::AUTH_HTTP_PASSWORD])) {
			list($login, $password) = $this->authorizeBy[self::AUTH_HTTP_PASSWORD];
			$headers['Authorization'] = sprintf('Basic %s', base64_encode($login . ':' . $password));

		} elseif (isset($this->authorizeBy[self::AUTH_URL_TOKEN])) {
			$params['access_token'] = $this->getAccessToken();

		} elseif ($token = $this->getAccessToken()) { // automatically sign by user's token if he's authorized
			$headers['Authorization'] = 'token ' . $token;
		}

		$this->authorizeBy = array();

		return array($params, $headers);
	}



	/**
	 * Allows you to write less code, because you won't have the get the parameters and pass them.
	 *
	 * <code>
	 * $response = $client->get('/applications/:client_id/tokens/:access_token');
	 * </code>
	 *
	 * @param $path
	 * @param $params
	 * @return Nette\Http\UrlScript
	 * @throws \Nette\Utils\RegexpException
	 */
	protected function buildRequestUrl($path, $params)
	{
		if (($q = stripos($path, '?')) !== FALSE) {
			$query = substr($path, $q + 1);
			parse_str($query, $params);
			$path = substr($path, 0, $q);
		}

		$url = $this->config->createUrl('api', $path, $params);
		if (substr_count($url->path, ':') === 0) { // no parameters
			return $url;
		}

		$client = $this;
		$url->setPath(Nette\Utils\Strings::replace($url->getPath(), '~(?<=\\/|^)\\:(\w+)(?=\\/|\\z)~i', function ($m) use ($client) {
			if ($m[1] === 'client_id') {
				return $client->config->appId;

			} elseif ($m[1] === 'client_secret') {
				return $client->config->appSecret;

			} elseif ($m[1] === 'user_id') {
				return $client->getUser();

			} elseif ($m[1] === 'access_token') {
				return $client->getAccessToken();

			} elseif ($m[1] === 'login' && $client->getUser()) {
				try {
					$user = $client->get('/user'); // the repeated call is cached on http client level
					return $user->login;

				} catch (ApiException $e) {
					return $m[0];
				}
			}

			return $m[0];
		}));

		return $url;
	}



	/**
	 * The next request will contain parameter `access_token` in the url.
	 *
	 * <code>
	 * $response = $client->authByUrlToken()->get('/users/whatever');
	 * // will generate https://api.github.com/users/whatever?access_token=xxx
	 * </code>
	 *
	 * Expects, that the user is authorized or that you've provided the `access_token` using
	 *
	 * <code>
	 * $client->setAccessToken($token);
	 * </code>
	 *
	 * @return Client
	 */
	public function authByUrlToken()
	{
		$this->authorizeBy = array(self::AUTH_URL_TOKEN => TRUE);
		return $this;
	}



	/**
	 * The next request will contain `client_id` and `client_secret` parameters,
	 * that will be automatically fetched from your config.
	 *
	 * <code>
	 * $response = $client->authByClientIdParameter()->get('/users/whatever');
	 * // will generate https://api.github.com/users/whatever?client_id=xxx&client_secret=yyy
	 * </code>
	 *
	 * @return Client
	 */
	public function authByClientIdParameter()
	{
		$this->authorizeBy = array(self::AUTH_URL_CLIENT_ID => TRUE);
		return $this;
	}



	/**
	 * The next request will be authorized by provided username and password.
	 *
	 * <code>
	 * $response = $client->authByPassword('user', 'password')->get('/users/whatever');
	 * // will generate https://user:password@api.github.com/users/whatever
	 * </code>
	 *
	 * @return Client
	 */
	public function authByPassword($login, $password)
	{
		$this->authorizeBy = array(self::AUTH_HTTP_PASSWORD => array($login, $password));
		return $this;
	}



	/**
	 * Sets the access token for api calls.  Use this if you get
	 * your access token by other means and just want the SDK
	 * to use it.
	 *
	 * @param string $accessToken an access token.
	 * @return Client
	 */
	public function setAccessToken($accessToken)
	{
		$this->accessToken = $accessToken;
		return $this;
	}



	/**
	 * Determines the access token that should be used for API calls.
	 * The first time this is called, $this->accessToken is set equal
	 * to either a valid user access token, or it's set to the application
	 * access token if a valid user access token wasn't available.  Subsequent
	 * calls return whatever the first call returned.
	 *
	 * @return string The access token
	 */
	public function getAccessToken()
	{
		if ($this->accessToken !== NULL) {
			return $this->accessToken; // we've done this already and cached it.  Just return.
		}

		if ($accessToken = $this->getUserAccessToken()) {
			$this->setAccessToken($accessToken);
		}

		return $this->accessToken;
	}



	/**
	 * @internal
	 * @return Api\CurlClient
	 */
	public function getHttpClient()
	{
		return $this->httpClient;
	}



	/**
	 * Determines and returns the user access token, first using
	 * the signed request if present, and then falling back on
	 * the authorization code if present.  The intent is to
	 * return a valid user access token, or false if one is determined
	 * to not be available.
	 *
	 * @return string A valid user access token, or false if one could not be determined.
	 */
	protected function getUserAccessToken()
	{
		if (($code = $this->getCode()) && $code != $this->session->code) {
			if ($accessToken = $this->getAccessTokenFromCode($code)) {
				$this->session->code = $code;
				return $this->session->access_token = $accessToken;
			}

			// code was bogus, so everything based on it should be invalidated.
			$this->session->clearAll();
			return FALSE;
		}

		// as a fallback, just return whatever is in the persistent
		// store, knowing nothing explicit (signed request, authorization
		// code, etc.) was present to shadow it (or we saw a code in $_REQUEST,
		// but it's the same as what's in the persistent store)
		return $this->session->access_token;
	}



	/**
	 * Determines the connected user by first examining any signed
	 * requests, then considering an authorization code, and then
	 * falling back to any persistent store storing the user.
	 *
	 * @return integer The id of the connected Github user, or 0 if no such user exists.
	 */
	protected function getUserFromAvailableData()
	{
		$user = $this->session->get('user_id', 0);

		// use access_token to fetch user id if we have a user access_token, or if
		// the cached access token has changed.
		if (($accessToken = $this->getAccessToken()) && !($user && $this->session->access_token === $accessToken)) {
			if (!$user = $this->getUserFromAccessToken()) {
				$this->session->clearAll();

			} else {
				$this->session->user_id = $user;
			}
		}

		return $user;
	}



	/**
	 * Get the authorization code from the query parameters, if it exists,
	 * and otherwise return false to signal no authorization code was
	 * discoverable.
	 *
	 * @return mixed The authorization code, or false if the authorization code could not be determined.
	 */
	protected function getCode()
	{
		$state = $this->getRequest('state');
		if (($code = $this->getRequest('code')) && $state && $this->session->state === $state) {
			$this->session->state = NULL; // CSRF state has done its job, so clear it
			return $code;
		}

		return FALSE;
	}



	/**
	 * Retrieves the UID with the understanding that $this->accessToken has already been set and is seemingly legitimate.
	 * It relies on Github's API to retrieve user information and then extract the user ID.
	 *
	 * @return integer Returns the UID of the Github user, or 0 if the Github user could not be determined.
	 */
	protected function getUserFromAccessToken()
	{
		try {
			$user = $this->get('/user');

			return isset($user['id']) ? $user['id'] : 0;
		} catch (\Exception $e) { }

		return 0;
	}



	/**
	 * Retrieves an access token for the given authorization code
	 * (previously generated from www.github.com on behalf of a specific user).
	 * The authorization code is sent to api.github.com/oauth
	 * and a legitimate access token is generated provided the access token
	 * and the user for which it was generated all match, and the user is
	 * either logged in to Github or has granted an offline access permission.
	 *
	 * @param string $code An authorization code.
	 * @param null $redirectUri
	 * @return mixed An access token exchanged for the authorization code, or false if an access token could not be generated.
	 */
	protected function getAccessTokenFromCode($code, $redirectUri = NULL)
	{
		if (empty($code)) {
			return FALSE;
		}

		if ($redirectUri === NULL) {
			$redirectUri = $this->getCurrentUrl();
			parse_str($redirectUri->getQuery(), $query);
			unset($query['code'], $query['state']);
			$redirectUri->setQuery($query);
		}

		try {
			$url = $this->config->createUrl('oauth', 'access_token', array(
				'client_id' => $this->config->appId,
				'client_secret' => $this->config->appSecret,
				'code' => $code,
				'redirect_uri' => $redirectUri,
			));

			$response = $this->httpClient->makeRequest(new Api\Request($url, Api\Request::POST, array(), array('Accept' => 'application/json')));
			if (!$response->isOk() || !$response->isJson()) {
				return FALSE;
			}

			$token = $response->toArray();

		} catch (\Exception $e) {
			// most likely that user very recently revoked authorization.
			// In any event, we don't have an access token, so say so.
			return FALSE;
		}

		return isset($token['access_token']) ? $token['access_token'] : FALSE;
	}



	/**
	 * Destroy the current session
	 */
	public function destroySession()
	{
		$this->accessToken = NULL;
		$this->user = NULL;
		$this->session->clearAll();
	}



	/**
	 * @param string $key
	 * @param mixed $default
	 * @return mixed|null
	 */
	protected function getRequest($key, $default = NULL)
	{
		if ($value = $this->httpRequest->getPost($key)) {
			return $value;
		}

		if ($value = $this->httpRequest->getQuery($key)) {
			return $value;
		}

		return $default;
	}

}
