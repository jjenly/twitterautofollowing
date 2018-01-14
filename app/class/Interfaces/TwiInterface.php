<?php
namespace App\Interfaces;
interface TwiInterface
{
    public function getCredits();
    public function getFriends(int $twitter_id);
    public function getFollowers(int $twitter_id);
    public function getUserInfo(int $twitter_id);
    public function getUserInfoByName(string $userScreenName);
    public function friendshipsLookup(string $users);
    public function friendshipsCreate(string $userScreenName);

}