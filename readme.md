Twitter Auto-Follow
------------

Twitter Auto-Follow is a **php script** for adding new follow requests.
Just install this application and run main.php

Installation
------------

* [Create credits][3] for your app.
* Install composer packages.
* Rename file .env-example to .env with your credits.
* Rename Settings.php-example into Settings.php with your configuration

How it works
------------
Configure this app in Settings.php

```
    //screen name for account that you will check
    const DONOR_SCREEN_NAME = 'XXXXX';
    //condition for account, if user will be ok, request will be send
    const FOLLOWERS_COUNT = ['min'=> 10, 'max'=> 100];
    const FRIENDS_COUNT = ['min'=> 10, 'max'=> 200];
    //send request total count
    const REQUEST_TOTAL = 10;
    //choose donor 'followers' or 'friends' will create your donor list for follow requests
    const ACTIVE_LIST_TYPE = 'followers';

```

App will get twitter user by username, then get all followers or friends from this account and will send request if user from donor list will be satisfied the conditions.
 
 Notes
 ------------
 
 By default all requests will be stored in send_request table.
 
 This table have field status:
 * 0 - request was not sended to user
 * 1 - request was sended to user
 * 2 - this user id a friend
 
 Logs folder must be writable for script.

Links
--------

* [My site][1]
* [Game for developers][2]
* [Twitter apps][3]



[1]: https://jenly.ru/
[2]: https://game.jenly.ru/
[3]: https://apps.twitter.com/
