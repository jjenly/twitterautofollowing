<?php

namespace App;

use App\Config\Settings;
use App\Interfaces\TwiInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class AutoFollow
{
    protected $me;
    protected $em;
    protected $donor;
    protected $donorList;
    protected $logger;
    protected $donorId;
    protected $apiTwitter;

    public function __construct(\MysqliDb $mysqliDb, TwiInterface $apiTwitter)
    {
        $this->em = $mysqliDb;
        $this->apiTwitter = $apiTwitter;
        $this->createSchema();
        $this->setUpMe();
        $this->setDonorInfo(Settings::DONOR_SCREEN_NAME);
        $this->setLogger();
        $this->logger->info("***********START*************");
    }

    function __destruct()
    {
        $this->logger->info("***********END*************");
    }

    protected function setUpMe()
    {
        $this->me = $this->apiTwitter->getCredits();
    }

    public function goFollow()
    {
        $totalSendRequest = 0;
        $this->logger->info("Total ids count: " . count($this->donorList->ids));
        foreach ($this->donorList->ids as $row => $twitterId) {
            if ($totalSendRequest > Settings::REQUEST_TOTAL) {
                $this->logger->info("limit reached: " . Settings::REQUEST_TOTAL);
                break(1);
            }
            if ($this->isSended($twitterId)) {
                $this->logger->notice("Already sended");
                continue;
            }
            $info = $this->apiTwitter->getUserInfo($twitterId);

            if ($this->isUserBad($info)) {
                $this->logger->notice("$info->screen_name ($info->id): condition is wrong");
                $this->logSendRequest($info, 0);
                continue;
            }
            if ($this->isUserInactive($info)) {
                $this->logger->notice("$info->screen_name ($info->id): user inactive");
                $this->logSendRequest($info, 0);
                continue;
            }

            if (!$this->sendRequest($info)) {
                $this->logSendRequest($info, 0);
                continue;
            };
            $this->logger->info("$info->screen_name ($info->id): added");
            $totalSendRequest++;
            $this->logSendRequest($info);
            sleep(Settings::SLEEP_AFTER_REQUEST);
        }

        $this->logger->info("total send: $totalSendRequest");
        $this->logger->info("total watch: $row");

    }

    protected function setDonorInfo(string $donorName)
    {
        try {
            $userInfo = $this->apiTwitter->getUserInfoByName($donorName);
            $this->setDonor($userInfo);
            $userList = (Settings::ACTIVE_LIST_TYPE === 'friends') ? $this->apiTwitter->getFriends($this->donor->id) : $this->apiTwitter->getFollowers($this->donor->id);
            $this->setDonorList($userList);
            $this->setDbDonorId($userInfo);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return true;
    }

    protected function isSended(int $twitterId)
    {
        $user = $this->getUserFromSended($twitterId);
        if (!$user) {
            return false;
        }
        return true;
    }

    protected function getUserFromSended(int $twitterId)
    {
        $this->em->where("twitter_id", $twitterId);
        return $this->em->getOne(Settings::SEND_REQUEST_TABLE);
    }


    protected function isUserBad($user)
    {
        if (
            Settings::FOLLOWERS_COUNT['min'] <= $user->followers_count &&
            $user->followers_count < Settings::FOLLOWERS_COUNT['max'] &&
            Settings::FRIENDS_COUNT['min'] <= $user->friends_count &&
            $user->friends_count < Settings::FRIENDS_COUNT['max'] &&
            $user->profile_use_background_image

        ) {
            return false;
        }
        return true;
    }

    protected function isUserInactive($user)
    {
        if (empty($user->status)) {
            return true;
        }

        $date = new \DateTime($user->status->created_at);
        $now = new \DateTime();
        $diff = date_diff($now, $date);
        if ($diff->days > Settings::USER_INACTIVE_AFTER_DAYS) {
            return true;
        }
        return false;
    }

    protected function logSendRequest($userInfo, $status = 1)
    {

        $data = [
            "twitter_id" => $userInfo->id,
            "screen_name" => $userInfo->screen_name,
            "donor_id" => $this->donorId,
            "list_type" => Settings::LIST_TYPE[Settings::ACTIVE_LIST_TYPE],
            "status" => $status
        ];
        $id = $this->em->insert(Settings::SEND_REQUEST_TABLE, $data);

        if ($id) {
            return true;
        }
        return false;
    }

    protected function setDbDonorId($userInfo)
    {
        $this->em->where("twitter_id", $userInfo->id);
        $donor = $this->em->getOne(Settings::DONOR_LIST_TABLE);

        if (!$donor) {
            $data = [
                "twitter_id" => $userInfo->id,
                "screen_name" => $userInfo->screen_name,
                "friends_count" => $userInfo->friends_count,
                "followers_count" => $userInfo->followers_count,
                "visible" => 1
            ];
            $donor['id'] = $this->em->insert(Settings::DONOR_LIST_TABLE, $data);
        }

        $this->donorId = $donor['id'];
        return true;
    }


    public function syncFriends()
    {
        $friends = $this->apiTwitter->getFriends($this->me->id);
        $get = Settings::FRIENDS_SYNC_COUNT;
        while (!empty($friends->ids)) {
            $firstCent = array_splice($friends->ids, 0, $get);
            $users = implode(",", $firstCent);
            $answer = $this->apiTwitter->friendshipsLookup($users);

            foreach ($answer as $user) {
                $sendedUser = $this->getUserFromSended($user->id);
                $isFollow = in_array('followed_by', $user->connections) ? 2 : 1;

                if (!$sendedUser) {
                    $info = $this->apiTwitter->getUserInfo($user->id);
                    $this->logSendRequest($info, $isFollow);
                } else {
                    if ((int)$sendedUser['status'] === (int)$isFollow) {
                        continue;
                    }
                    $sendedUser['status'] = $isFollow;
                    $this->em->where('id', $sendedUser['id']);
                    $this->em->update(Settings::SEND_REQUEST_TABLE, $sendedUser);
                }
            }
        }

        return $this;
    }


    protected function sendRequest($userInfo)
    {
        $answer = $this->apiTwitter->friendshipsCreate($userInfo->screen_name);
        if (!empty($answer->errors)) {
            $this->logger->error("$userInfo->id: cant add " . json_encode($answer->errors));
            return false;
        }
        return true;
    }

    /**
     * @param array|object $donor
     */
    protected function setDonor($donor): void
    {
        $this->donor = $donor;
    }

    /**
     * @param mixed $donorList
     */
    protected function setDonorList($donorList): void
    {
        $this->donorList = $donorList;
    }

    protected function setLogger()
    {
        $filename = date('Y-m-d-H-i') . '-' . $this->donor->screen_name . '.log';
        $this->logger = new Logger('reqStatus');
        $this->logger->pushHandler(new StreamHandler('/var/www/apps/autofollow/html/logs/' . $filename, Logger::DEBUG));
    }

    protected function createSchema()
    {
        $this->em->rawQuery(
            'CREATE TABLE IF NOT EXISTS `' . Settings::DONOR_LIST_TABLE . '` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `twitter_id` bigint(20) NOT NULL,
              `screen_name` varchar(30) NOT NULL,
              `friends_count` int(11) NOT NULL DEFAULT \'0\',
              `followers_count` int(11) NOT NULL DEFAULT \'0\',
              `visible` char(1) NOT NULL DEFAULT \'1\',
              PRIMARY KEY (`id`),
              UNIQUE KEY `in_d_twitter` (`twitter_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;'
        );


        $this->em->rawQuery(
            'CREATE TABLE IF NOT EXISTS `' . Settings::SEND_REQUEST_TABLE . '` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `donor_id` int(11) NOT NULL,
              `twitter_id` bigint(20) NOT NULL,
              `screen_name` varchar(30) NOT NULL,
              `list_type` tinyint(1) NOT NULL DEFAULT \'1\',
              `status` char(1) NOT NULL DEFAULT \'1\',
              PRIMARY KEY (`id`),
              UNIQUE KEY `in_tw_req` (`twitter_id`),
              UNIQUE KEY `in_name_req` (`screen_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;'
        );


        return $this;
    }

}