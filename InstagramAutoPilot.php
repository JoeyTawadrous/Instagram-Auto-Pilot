<?php
require("../../lib/vendor/autoload.php");

class InstagramAutoPilot {

    public function init($ACC_NAME, $ACC_PASS, $GET_NEW_IMAGES, $FOLLOW_USERS, $UNFOLLOW_USERS, $ACCOUNTS, $TAGS, $COMMENT) {
        $instagram = new \Instagram\Instagram();
        $instagram->login($ACC_NAME, $ACC_PASS);

        // so that the script cron job with not always start at the exact same time it's scheduled
        // sleep(rand(32, 50));

        if($GET_NEW_IMAGES == true) { $this->getNewImage($ACC_NAME, $instagram, $ACCOUNTS, $TAGS); } 

        if($FOLLOW_USERS == true) { $this->followUsers($instagram, $ACCOUNTS, $ACC_NAME); }

        if($UNFOLLOW_USERS == true) { $this->unfollowUsers($instagram, $ACC_NAME); }

        if($COMMENT != "") { $this->comment($instagram, $ACCOUNTS, $COMMENT); }
    }



    /* MARK: Core Functionality    */
    /*******************************/
    function getNewImage($ACC_NAME, $instagram, $accounts, $tags) {
        $keepGoing = true;
        $randomAccount = array($accounts[rand(0, (sizeof($accounts) - 1))]);
        $user = $instagram->getUserByUsername($randomAccount[0]);
        $userFeed = $instagram->getUserFeed($user);
        $userFeed = $instagram->getUserFeed($user, $userFeed->getNextMaxId()); // page 2

        foreach($userFeed->getItems() as $feedItem){
            $idOfImage = $feedItem->getId(); //Feed Item ID
            $images = $feedItem->getImageVersions2()->getCandidates(); //Grab a list of Images for this Post (different sizes)
            $photoUrl = $images[0]->getUrl(); //Grab the URL of the first Photo in the list of Images for this Post

            if($keepGoing) { // keep going until we find a photo not uploaded before
                $filename = "repostedFrom/" . $randomAccount[0] . ".txt";
                $keepGoing = $this->checkIfIdSeenBefore($filename, $idOfImage);

                if($keepGoing == false) {
                    $photoLocationOnDisk = "imagesUploaded/" . $idOfImage . ".jpg";
                    
                    copy($photoUrl, $photoLocationOnDisk); // copy image to local dir

                    $caption = $feedItem->getCaption()->getText();
                    
                    $instagram->postPhoto($photoLocationOnDisk, $caption); // upload photo

                    $file = "";
                    if(!file_exists($filename)) { 
                       $file = fopen($filename, "w");  
                    }  
                    else {
                       $file = fopen($filename, "a");  
                    }
                    fwrite($file, "\n" . $idOfImage);  
                    fclose($file); 


                    $this->writeToLogs("\n\Reposted another image. [" . date("Y-m-d h:i:sa", time()) . "]");
                }
            }
        }
    }

    function comment($instagram, $accounts, $comment) {
        $maxDelayTime = 15; // Set the max delay in seconds between api requests
        $totalCommentsToMake = rand(9, 30);
        $keepGoing = true;
        $randomAccount = array($accounts[rand(0, (sizeof($accounts) - 1))]);
        $user = $instagram->getUserByUsername($randomAccount[0]);
      
        $followers = $instagram->getUserFollowers($user);
        foreach($followers->getFollowers() as $follower) {
           
            $userFeed = $instagram->getUserFeed($follower);

            if(sizeof($userFeed->getItems()) > 0) {
                foreach($userFeed->getItems() as $feedItem){
                    $idOfImage = $feedItem->getId(); //Feed Item ID
                    $images = $feedItem->getImageVersions2()->getCandidates(); //Grab a list of Images for this Post (different sizes)
                    $photoUrl = $images[0]->getUrl(); //Grab the URL of the first Photo in the list of Images for this Post

                    if($keepGoing) { // keep going until we find a user who's photo we've not commented on before
                        $filename = "comments.txt";
                        $keepGoing = $this->checkIfIdSeenBefore($filename, $follower->getUsername());

                        if($keepGoing == false) {
                                if($totalCommentsToMake > 0) {
                                $instagram->commentOnMedia($idOfImage, $comment);

                                // $photoLocationOnDisk = "imagesCommented/" . $idOfImage . ".jpg";
                                // copy($photoUrl, $photoLocationOnDisk); // copy image to local dir

                                $file = "";
                                if(!file_exists($filename)) { 
                                   $file = fopen($filename, "w");  
                                }  
                                else {
                                   $file = fopen($filename, "a");  
                                }
                                fwrite($file, "\n" . $follower->getUsername());  
                                fclose($file); 

                                // reset
                                $keepGoing = true;
                                $totalCommentsToMake--;

                                $delayTime = rand(8, $maxDelayTime);

                                $this->writeToLogs("\nCommented on " . $follower->getUsername() . ". Sleeping for " . $delayTime . " seconds. [" . date("Y-m-d h:i:sa", time()) . "]");

                                sleep($delayTime);
                            }
                        }
                    }
                }
            }
        }
    }

    function followUsers($instagram, $accounts, $accountName) {
        $maxDelayTime = 15; // Set the max delay in seconds between api requests (following or unfollowing)
        $maxFollow = rand(40, 80); // Set the max amount of users to follow in one run of this script
        $shouldFollow = rand(0, 1);
        $randomAccount = array($accounts[rand(0, 10)]);
        $user = $instagram->getUserByUsername($randomAccount[0]);
        $followers = $instagram->getUserFollowers($user);

        $myAccount = $instagram->getUserByUsername($accountName);
        $alreadyfollowing = $instagram->getUserFollowing($myAccount);

        if($shouldFollow == 1) {
            $this->writeToLogs("\n\nAbout To Follow New Users...");

            foreach($followers->getFollowers() as $toFollow) {

                // if not already following user
                if(!in_array($toFollow, $alreadyfollowing->getFollowers()) && $maxFollow > 0) {
                    $instagram->followUser($toFollow);
                    
                    $delayTime = rand(8, $maxDelayTime);

                    $this->writeToLogs("\nFollowed " . $toFollow->getUsername() . " Sleeping for " . $delayTime . " seconds. [" . date("Y-m-d h:i:sa", time()) . "]");
                    $maxFollow--;

                    sleep($delayTime);
                }
            }
        }
    }

    function unfollowUsers($instagram, $accountName) {
        $maxDelayTime = 15; // Set the max delay in seconds between api requests (following or unfollowing)
        $maxUnfollow = rand(20, 240); // Set the max amount of users to unfollow in one run of this script

        $myAccount = $instagram->getUserByUsername($accountName);
        $alreadyfollowing = $instagram->getUserFollowing($myAccount);

        $this->writeToLogs("\n\nAbout To Unfollow Users...");

        foreach($alreadyfollowing->getFollowers() as $toUnfollow) {
            if($maxUnfollow > 0) {
                $instagram->unfollowUser($toUnfollow);
                $maxUnfollow--;
                
                $delayTime = rand(8, $maxDelayTime);

                $this->writeToLogs("\nUnfollowed " . $toUnfollow->getUsername() . " Sleeping for " . $delayTime . " seconds. [" . date("Y-m-d h:i:sa", time()) . "]");
                
                sleep($delayTime);
            }
        }
    }

    function getRandomTags($tags) {
        $usedTags = array();
        $tagsString = "";

        if(sizeof($tags) > 20) {
            for($i = 0; $i < 21; $i++) {
                $randomTag = $tags[array_rand($tags)];
                if(!in_array($randomTag, $usedTags)) {
                    $tagsString .= " #" . $randomTag;
                    array_push($usedTags, $randomTag);
                }
            }
        }
        else {
            $tagsString = implode(" ", $tags);
        }

        return $tagsString;
    }

    /**
    * Checks txt file to see if an id is already there.. meaning it was seen before
    **/
    function checkIfIdSeenBefore($filename, $id) {
        $file = @fopen($filename, "r");
        $idSeen = false;

        if ($file) {
            while (($line = fgets($file)) !== false) {
                $line = str_replace("\n", "", $line); // hidden newline char at end of line

                if(strcmp($id, $line) === 0) {
                    $idSeen = true;
                }
            }

            fclose($file);
        } 
        else {
            // error opening the file.
        } 

        return $idSeen;
    }   

    function writeToLogs($textToWrite) {
        $currentfile = "Logs.txt";
        $updatedFile = file_get_contents($currentfile);
        $updatedFile .= $textToWrite;
        file_put_contents($currentfile, $updatedFile);
    }  
}
?>
