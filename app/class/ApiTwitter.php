<?php

namespace App;

use Abraham\TwitterOAuth\TwitterOAuth;
use App\Interfaces\TwiInterface;

class ApiTwitter implements TwiInterface
{
    protected $connection;

    public function __construct()
    {
        $this->connection = new TwitterOAuth(getenv('CONSUMER_KEY'), getenv('CONSUMER_SECRET'), getenv('ACCESS_TOKEN'), getenv('ACCESS_TOKEN_SECRET'));
    }


    public function getCredits()
    {
        return $this->connection->get("account/verify_credentials");
    }

    public function getFriends(int $twitter_id)
    {
        return $this->connection->get("friends/ids", ['user_id' => $twitter_id]);
    }

    public function getFollowers(int $twitter_id)
    {
        return $this->connection->get("followers/ids", ['user_id' => $twitter_id]);
    }

    public function getUserInfo(int $twitter_id)
    {
        return $this->connection->get("users/show", ['user_id' => $twitter_id]);
    }

    public function getUserInfoByName(string $userScreenName)
    {
        return $this->connection->get("users/show", ['screen_name' => $userScreenName]);
    }

    public function friendshipsLookup(string $users)
    {
        return $this->connection->get("friendships/lookup", ['user_id' => $users]);
    }

    public function friendshipsCreate(string $userScreenName)
    {
        return $this->connection->post("friendships/create", ['screen_name' => $userScreenName]);
    }
}