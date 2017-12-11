<?php

class B2S_Ship_Save {

    public $postData;

    public function __construct() {
        $this->postData = array();
    }

    private function getNetworkDetailsId($network_id, $network_type, $network_auth_id, $network_display_name) {
        global $wpdb;
        $networkDetailsIdSelect = $wpdb->get_col($wpdb->prepare("SELECT postNetworkDetails.id FROM b2s_posts_network_details AS postNetworkDetails WHERE postNetworkDetails.network_auth_id = %s", $network_auth_id));
        if (isset($networkDetailsIdSelect[0])) {
            return (int) $networkDetailsIdSelect[0];
        } else {
            $wpdb->insert('b2s_posts_network_details', array(
                'network_id' => (int) $network_id,
                'network_type' => (int) $network_type,
                'network_auth_id' => (int) $network_auth_id,
                'network_display_name' => $network_display_name), array('%d', '%d', '%d', '%s'));
            return $wpdb->insert_id;
        }
    }

    public function savePublishDetails($data) {
        global $wpdb;
        $networkDetailsId = $this->getNetworkDetailsId($data['network_id'], $data['network_type'], $data['network_auth_id'], $data['network_display_name']);

//unset($data['network_id']);
        unset($data['network_type']);
        unset($data['network_display_name']);

        $postData = array(
            'post_id' => $data['post_id'],
            'blog_user_id' => $data['blog_user_id'],
            'user_timezone' => $data['user_timezone'],
            'publish_date' => $data['publish_date'],
            'network_details_id' => $networkDetailsId
        );
        $wpdb->insert('b2s_posts', $postData, array('%d', '%d', '%d', '%s', '%d'));
        B2S_Rating::trigger();

        $data['internal_post_id'] = $wpdb->insert_id;
        $this->postData['token'] = $data['token'];
        $this->postData["blog_user_id"] = $data["blog_user_id"];
        $this->postData["post_id"] = $data["post_id"];
        $this->postData["default_titel"] = $data["default_titel"];
        $this->postData["lang"] = $data["lang"];
        $this->postData['user_timezone'] = $data['user_timezone'];

        unset($data['token']);
        unset($data['blog_user_id']);
        unset($data['post_id']);
        unset($data['default_titel']);
        unset($data['lang']);
        unset($data['user_timezone']);
        unset($data['publish_date']);

        $this->postData['post'][] = $data;
    }

    public function postPublish() {
        global $wpdb;
        $content = array();
        $this->postData['action'] = 'sentToNetwork';
        $postData = $this->postData['post'];
        $this->postData['post'] = serialize($this->postData['post']);
        $result = json_decode(B2S_Api_Post::post(B2S_PLUGIN_API_ENDPOINT, $this->postData));

        foreach ($postData as $k => $v) {
            $found = false;
            $networkId = (isset($v['network_id']) && (int) $v['network_id'] > 0) ? (int) $v['network_id'] : 0;
            if (isset($result->data) && is_array($result->data)) {
                foreach ($result->data as $key => $post) {
                    if (isset($post->internal_post_id) && (int) $post->internal_post_id == (int) $v['internal_post_id']) {
                        $data = array('publish_link' => $post->publishUrl, 'publish_error_code' => isset($post->error_code) ? $post->error_code : '');
                        $where = array('id' => $post->internal_post_id);
                        $wpdb->update('b2s_posts', $data, $where, array('%s', '%s'), array('%d'));
                        $errorCode = isset($post->error_code) ? $post->error_code : '';
                        $content[] = array('networkAuthId' => $post->network_auth_id, 'html' => $this->getItemHtml($networkId, $errorCode, $post->publishUrl));
                        $found = true;
                    }
                }
            }
//DEFAULT ERROR
            if ($found == false) {
                $content[] = array('networkAuthId' => $v['network_auth_id'], 'html' => $this->getItemHtml($networkId, 'DEFAULT', ''));
            }
        }
        return $content;
    }

    public function saveSchedDetails($data, $schedData) {
        global $wpdb;

        $networkDetailsId = $this->getNetworkDetailsId($data['network_id'], $data['network_type'], $data['network_auth_id'], $data['network_display_name']);
        $serializeData = $data;

        //unset($serializeData['network_id']);
        unset($serializeData['network_type']);
        unset($serializeData['network_display_name']);
        unset($serializeData['token']);
        unset($serializeData['blog_user_id']);
        unset($serializeData['post_id']);
        unset($serializeData['image']);

        if (isset($data['sched_details_id'])) {
            $wpdb->update('b2s_posts_sched_details', array(
                'sched_data' => serialize($serializeData),
                'image_url' => $data['image_url']
                    ), array("id" => $data['sched_details_id']), array('%s', '%s', '%d'));
            $schedDetailsId = $data['sched_details_id'];
        } else {
            $wpdb->insert('b2s_posts_sched_details', array('sched_data' => serialize($serializeData), 'image_url' => $data['image_url']), array('%s', '%s'));
            $schedDetailsId = $wpdb->insert_id;
        }

        $printSchedDate = array();
        //mode: once schedule
        if ($schedData['releaseSelect'] == 1 && is_array($schedData['date']) && isset($schedData['date'][0]) && !empty($schedData['date'][0]) && isset($schedData['time'][0]) && !empty($schedData['time'][0])) {
            foreach ($schedData['date'] as $key => $date) {
                $sendTime = strtotime($date . ' ' . $schedData['time'][$key]);
                $shipdays[] = array('sched_date' => date('Y-m-d H:i:00', $sendTime), 'sched_date_utc' => date('Y-m-d H:i:00', strtotime(B2S_Util::getUTCForDate($date . ' ' . $schedData['time'][$key], $schedData['user_timezone'] * (-1)))));
                $printSchedDate[] = date('Y-m-d H:i:s', $sendTime);
                if ($schedData['saveSetting']) {
                    $this->saveUserDefaultSettings(date('H:i', $sendTime), $data['network_id'], $data['network_type']);
                }
            }
        } else {
            //mode: recurrently schedule
            if (isset($schedData['interval_select']) && is_array($schedData['interval_select']) && isset($schedData['interval_select'][0])) {
                $dayOfWeeks = array(1 => 'mo', 2 => 'di', 3 => 'mi', 4 => 'do', 5 => 'fr', 6 => 'sa', 7 => 'so');
                $shipdays = array();
                foreach ($schedData['interval_select'] as $cycle => $mode) {
                    //interval:weekly
                    if ((int) $mode == 0) {
                        foreach ($dayOfWeeks as $dayNumber => $dayName) {
                            if (isset($schedData[$dayName][$cycle]) && $schedData[$dayName][$cycle] == 1) {
                                for ($weeks = 1; $weeks <= $schedData['weeks'][$cycle]; $weeks++) {
                                    $startTime = (isset($schedData['date'][$cycle]) && isset($schedData['time'][$cycle])) ? $schedData['date'][$cycle] : $data['publish_date'];
                                    $startDay = date('N', strtotime($startTime));
                                    $maxDaysSched = $schedData['weeks'][$cycle] * 7 + $startDay;
                                    if ($dayNumber < $startDay) {
                                        if ($schedData['weeks'][$cycle] == 1) {
                                            $sendDay = 7 - $startDay + $dayNumber;
                                        } else {
                                            $sendDay = 7 - $startDay + $dayNumber + (7 * ($weeks - 1));
                                        }
                                    } else if ($dayNumber == $startDay) {
                                        $sendDay = (7 * ($weeks - 1));
                                    } else {
                                        $sendDay = $dayNumber - $startDay + (7 * ($weeks - 1));
                                    }
                                    if ($schedData['weeks'][$cycle] == 1 || $sendDay <= $maxDaysSched) {
                                        $schedTime = date('Y-m-d', strtotime("+$sendDay days", strtotime($startTime)));
                                        $tempSchedDateTime = date('Y-m-d H:i:00', strtotime($schedTime . ' ' . $schedData['time'][$cycle]));
                                        $sched_date_utc = date('Y-m-d H:i:00', strtotime(B2S_Util::getUTCForDate($tempSchedDateTime, $schedData['user_timezone'] * (-1))));
                                        if ($tempSchedDateTime >= $data['publish_date']) {
                                            $shipdays[] = array('sched_date' => $tempSchedDateTime, 'sched_date_utc' => $sched_date_utc);
                                            $printSchedDate[] = $tempSchedDateTime;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    //interval:monthly
                    if ((int) $mode == 1) {
                        if (isset($schedData['duration_month'][$cycle]) && isset($schedData['select_day'][$cycle]) && isset($schedData['date'][$cycle]) && isset($schedData['time'][$cycle])) {
                            $result = $this->createMonthlyIntervalDates($schedData['duration_month'][$cycle], $schedData['select_day'][$cycle], $schedData['date'][$cycle], $schedData['time'][$cycle]);
                            if (is_array($result) && !empty($result)) {
                                foreach ($result as $key => $date) { //Y-m-d none utc
                                    $sched_date_time = date('Y-m-d H:i:00', strtotime($date . ' ' . $schedData['time'][$cycle]));
                                    $sched_date_time_utc = date('Y-m-d H:i:00', strtotime(B2S_Util::getUTCForDate($sched_date_time, $schedData['user_timezone'] * (-1))));
                                    $shipdays[] = array('sched_date' => $sched_date_time, 'sched_date_utc' => $sched_date_time_utc);
                                    $printSchedDate[] = $sched_date_time;
                                }
                            }
                        }
                    }
                    //interval: own period
                    if ((int) $mode == 2) {
                        if (isset($schedData['duration_time'][$cycle]) && isset($schedData['select_timespan'][$cycle]) && isset($schedData['date'][$cycle]) && isset($schedData['time'][$cycle])) {
                            $result = $this->createCustomIntervalDates($schedData['duration_time'][$cycle], $schedData['select_timespan'][$cycle], $schedData['date'][$cycle]);
                            if (is_array($result) && !empty($result)) {
                                foreach ($result as $key => $date) { //Y-m-d none utc
                                    $sched_date_time = date('Y-m-d H:i:00', strtotime($date . ' ' . $schedData['time'][$cycle]));
                                    $sched_date_time_utc = date('Y-m-d H:i:00', strtotime(B2S_Util::getUTCForDate($sched_date_time, $schedData['user_timezone'] * (-1))));
                                    $shipdays[] = array('sched_date' => $sched_date_time, 'sched_date_utc' => $sched_date_time_utc);
                                    $printSchedDate[] = $sched_date_time;
                                }
                            }
                        }
                    }
                }
            }

            /* $schedcount = count($schedData['weeks']);
              $dayOfWeeks = array(1 => 'mo', 2 => 'di', 3 => 'mi', 4 => 'do', 5 => 'fr', 6 => 'sa', 7 => 'so');
              $shipdays = array();
              for ($schedcycle = 0; $schedcycle < $schedcount; $schedcycle++) {
              foreach ($dayOfWeeks as $dayNumber => $dayName) {
              if (isset($schedData[$dayName][$schedcycle]) && $schedData[$dayName][$schedcycle] == 1) {
              for ($weeks = 1; $weeks <= $schedData['weeks'][$schedcycle]; $weeks++) {
              $startTime = (isset($schedData['date'][$schedcycle]) && isset($schedData['time'][$schedcycle])) ? $schedData['date'][$schedcycle] : $data['publish_date'];
              $startDay = date('N', strtotime($startTime));
              $maxDaysSched = $schedData['weeks'][$schedcycle] * 7 + $startDay;
              if ($dayNumber < $startDay) {
              if ($schedData['weeks'][$schedcycle] == 1) {
              $sendDay = 7 - $startDay + $dayNumber;
              } else {
              $sendDay = 7 - $startDay + $dayNumber + (7 * ($weeks - 1));
              }
              } else if ($dayNumber == $startDay) {
              $sendDay = (7 * ($weeks - 1));
              } else {
              $sendDay = $dayNumber - $startDay + (7 * ($weeks - 1));
              }
              if ($schedData['weeks'][$schedcycle] == 1 || $sendDay <= $maxDaysSched) {
              $schedTime = date('Y-m-d', strtotime("+$sendDay days", strtotime($startTime)));
              $tempSchedDateTime = date('Y-m-d H:i:00', strtotime($schedTime . ' ' . $schedData['time'][$schedcycle]));
              $sched_date_utc = date('Y-m-d H:i:00', strtotime(B2S_Util::getUTCForDate($tempSchedDateTime, $schedData['user_timezone'] * (-1))));
              if ($tempSchedDateTime >= $data['publish_date']) {
              $shipdays[] = array('sched_date' => $tempSchedDateTime, 'sched_date_utc' => $sched_date_utc);
              $printSchedDate[] = $tempSchedDateTime;
              }
              }
              }
              }
              }
              } */
        }

        foreach ($shipdays as $k => $schedDate) {
            if (isset($data['b2s_id']) && $data['b2s_id'] > 0) {
                $wpdb->update('b2s_posts', array(
                    'post_id' => $data['post_id'],
                    'blog_user_id' => $data['blog_user_id'],
                    'user_timezone' => $schedData['user_timezone'],
                    'publish_date' => "0000-00-00 00:00:00",
                    'sched_details_id' => $schedDetailsId,
                    'sched_type' => $schedData['releaseSelect'],
                    'sched_date' => $schedDate['sched_date'],
                    'sched_date_utc' => $schedDate['sched_date_utc'],
                    'network_details_id' => $networkDetailsId,
                    'hook_action' => 5
                        ), array("id" => $data['b2s_id']), array('%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d'));
            } else {
                $wpdb->insert('b2s_posts', array(
                    'post_id' => $data['post_id'],
                    'blog_user_id' => $data['blog_user_id'],
                    'user_timezone' => $schedData['user_timezone'],
                    'publish_date' => "0000-00-00 00:00:00",
                    'sched_details_id' => $schedDetailsId,
                    'sched_type' => $schedData['releaseSelect'],
                    'sched_date' => $schedDate['sched_date'],
                    'sched_date_utc' => $schedDate['sched_date_utc'],
                    'network_details_id' => $networkDetailsId,
                    'hook_action' => 1
                        ), array('%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d'));
                B2S_Rating::trigger();
            }
        }

        return array('networkAuthId' => $data['network_auth_id'], 'html' => $this->getItemHtml($serializeData['network_id'], '', '', $printSchedDate));
    }

    public function getItemHtml($network_id = 0, $error = "", $link = "", $schedDate = array()) {
        $html = "";
        if (empty($error)) {
            if (empty($schedDate)) {
                $html = '<br><span class="text-success"><i class="glyphicon glyphicon-ok-circle"></i> ' . __('published', 'blog2social');
                $html .=!empty($link) ? ': <a href="' . $link . '" target="_blank">' . __('view social media post', 'blog2social') . '</a>' : '';
                $html .='</span>';
            } else {
                if (is_array($schedDate)) {
                    $dateFormat = get_option('date_format');
                    $timeFormat = get_option('time_format');
                    sort($schedDate);
                    foreach ($schedDate as $k => $v) {
                        $schedDateTime = date_i18n($dateFormat . ' ' . $timeFormat, strtotime($v));
                        $html .= '<br><span class="text-success"><i class="glyphicon glyphicon-time"></i> ' . __('scheduled on', 'blog2social') . ': ' . $schedDateTime . '</span>';
                    }
                }
            }
        } else {
            $errorText = unserialize(B2S_PLUGIN_NETWORK_ERROR);
            $error = isset($errorText[$error]) ? $error : 'DEFAULT';
            $add = '';
//special case: reddit RATE_LIMIT
            if ($network_id == 15 && $error == 'RATE_LIMIT') {
                $link = (strtolower(substr(B2S_LANGUAGE, 0, 2)) == 'de') ? 'https://www.blog2social.com/de/faq/content/9/115/de/reddit-du-hast-das-veroeffentlichungs_limit-mit-deinem-account-kurzzeitig-erreicht.html' : 'https://www.blog2social.com/en/faq/content/9/115/en/reddit-you-have-temporarily-reached-the-publication-limit-with-your-account.html';
                $add = ' ' . __('Please see', 'blog2social') . ' <a target="_blank" href="' . $link . '">' . __('FAQ', 'blog2social') . '</a>';
            }

            $html .= '<br><span class="text-danger"><i class="glyphicon glyphicon-remove-circle glyphicon-danger"></i> ' . $errorText[$error] . $add . '</span>';
        }
        return $html;
    }

    private function saveUserDefaultSettings($schedTime, $networkId, $networkType) {
        global $wpdb;
        $settingsId = $wpdb->get_var($wpdb->prepare("SELECT id FROM b2s_post_sched_settings WHERE blog_user_id= %d AND network_id=%d AND network_type=%d", B2S_PLUGIN_BLOG_USER_ID, (int) $networkId, (int) $networkType));
        if ((int) $settingsId > 0) {
            $wpdb->update('b2s_post_sched_settings', array('sched_time' => $schedTime), array('id' => $settingsId), array('%s'), array('%d'));
        } else {
            $wpdb->insert('b2s_post_sched_settings', array('blog_user_id' => B2S_PLUGIN_BLOG_USER_ID, 'network_id' => $networkId, 'network_type' => (int) $networkType, 'sched_time' => $schedTime), array('%d', '%d', '%d', '%s'));
        }
    }

    //monthly
    public function createMonthlyIntervalDates($duration_month = 0, $select_day = 0, $date = "", $time = "") {
        $dates = array();
        $startDateTime = strtotime($date . ' ' . $time);
        $allowEndofMonth = ((int) $select_day == 0) ? true : false;
        $select_day = $allowEndofMonth ? 31 : sprintf("%02d", $select_day);
        $selectDateTime = strtotime(date('Y-m', $startDateTime) . '-' . $select_day . ' ' . $time);
        $addMonth = ($selectDateTime <= $startDateTime) ? 1 : 0;

        for ($i = 1; $i <= $duration_month; $i++) {
            $cDate = date('Y-m', strtotime(date('Y-m', $startDateTime) . " +" . $addMonth . " month"));
            if (checkdate((int) date('m', strtotime($cDate)), (int) $select_day, (int) date('Y', strtotime($cDate)))) {
                $dates[] = $cDate . "-" . $select_day;
            } else {
                //set last day of month
                if ($allowEndofMonth) {
                    $dates[] = date("Y-m-t", strtotime($cDate . "-01"));
                }
            }
            $addMonth++;
        }
        return $dates;
    }

    //own period
    public function createCustomIntervalDates($duration_time = 0, $select_timespan = 0, $date = "") {
        $dates = array();
        $cTimespan = $select_timespan;
        for ($i = 1; $i <= $duration_time; $i++) {
            $dates[] = date('Y-m-d', strtotime($date . " +" . $cTimespan . " day"));
            $cTimespan += $select_timespan;
        }
        return $dates;
    }

}
