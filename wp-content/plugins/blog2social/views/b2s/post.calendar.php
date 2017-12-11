<?php
/* Data */
require_once (B2S_PLUGIN_DIR . 'includes/B2S/Calendar/Filter.php');
require_once (B2S_PLUGIN_DIR . 'includes/B2S/Post/Filter.php');
require_once (B2S_PLUGIN_DIR . 'includes/B2S/Ship/Image.php');
require_once (B2S_PLUGIN_DIR . 'includes/B2S/Settings/Item.php');
require_once (B2S_PLUGIN_DIR . 'includes/Util.php');

$options = new B2S_Options(B2S_PLUGIN_BLOG_USER_ID);
$optionUserTimeZone = $options->_getOption('user_time_zone');
$userTimeZone = ($optionUserTimeZone !== false) ? $optionUserTimeZone : get_option('timezone_string');
$userTimeZoneOffset = (empty($userTimeZone)) ? get_option('gmt_offset') : B2S_Util::getOffsetToUtcByTimeZone($userTimeZone);
$metaSettings = get_option('B2S_PLUGIN_GENERAL_OPTIONS');
?>


<div class="b2s-container">
    <div class="b2s-inbox">
        <?php require_once (B2S_PLUGIN_DIR . 'views/b2s/html/header.phtml'); ?>
        <div class="col-md-12 del-padding-left">
            <div class="col-md-9 del-padding-left">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <div class="grid">
                            <div class="grid-body">
                                <?php require_once (B2S_PLUGIN_DIR . 'views/b2s/html/post.navbar.phtml'); ?>
                                <div class="clearfix"></div>
                                <div class="col-md-12 b2s-calendar-filter form-inline del-padding-left del-padding-right">
                                    <div class="b2s-calendar-filter-network-legend-text">
                                        <?php _e('Sort by network', 'blog2social'); ?>
                                    </div>
                                    <div class="clearfix"></div>
                                    <?php
                                    $filter = new B2S_Calendar_Filter();
                                    $filterNetwork = $filter->getNetworkHtml();
                                    if (!empty($filterNetwork)) {
                                        ?>
                                        <div class="b2s-calendar-filter-network-list">
                                            <?php echo $filterNetwork ?>
                                        </div>
                                        <div class="b2s-calendar-filter-network-account-list"></div>
                                    <?php }
                                    ?>
                                </div>  
                                <div class="clearfix"></div><hr>
                                <div class="b2s-loading-area">
                                    <br>
                                    <div class="b2s-loader-impulse b2s-loader-impulse-md"></div>
                                    <div class="clearfix"></div>
                                    <div class="text-center b2s-loader-text"><?php _e("Loading...", "blog2social"); ?></div>
                                </div>
                                <div id='b2s_calendar'></div>
                                <br>
                                <script>
                                    var b2s_calendar_locale = '<?= strtolower(substr(get_locale(), 0, 2)); ?>';
                                    var b2s_calendar_date = '<?= B2S_Util::getbyIdentLocalDate($userTimeZoneOffset, "Y-m-d"); ?>';
                                    var b2s_calendar_datetime = '<?= B2S_Util::getbyIdentLocalDate($userTimeZoneOffset); ?>';
                                    var b2s_has_premium = <?= B2S_PLUGIN_USER_VERSION > 0 ? "true" : "false"; ?>;
                                    var b2s_plugin_url = '<?= B2S_PLUGIN_URL; ?>';
                                    var b2s_calendar_formats = <?= json_encode(array('post' => array(__('Link Post', 'blog2social'), __('Photo Post', 'blog2social')), 'image' => array(__('Image with frame'), __('Image cut out')))); ?>;
                                    var b2s_is_calendar = true;
                                </script>
                            </div>
                        </div>
                        <?php
                        require_once (B2S_PLUGIN_DIR . 'views/b2s/html/footer.phtml');
                        ?> 
                    </div>
                </div>
            </div>
            <?php require_once (B2S_PLUGIN_DIR . 'views/b2s/html/service.phtml'); ?>
            <?php require_once (B2S_PLUGIN_DIR . 'views/b2s/html/sidebar.phtml'); ?>
        </div>
    </div>
</div>

<input type="hidden" id="b2sLang" value="<?php echo substr(B2S_LANGUAGE, 0, 2); ?>">
<input type="hidden" id="b2sJSTextAddPost" value="<?php echo _e("add post","blog2social");?>">                    
<input type="hidden" id="b2sUserLang" value="<?php echo strtolower(substr(get_locale(), 0, 2)); ?>">
<input type='hidden' id="user_timezone" name="user_timezone" value="<?php echo $userTimeZoneOffset; ?>">
<input type="hidden" id="user_version" name="user_version" value="<?php echo B2S_PLUGIN_USER_VERSION; ?>">
<input type="hidden" id="b2sDefaultNoImage" value="<?php echo plugins_url('/assets/images/no-image.png', B2S_PLUGIN_FILE); ?>">
<input type="hidden" id="b2sPostId" value="">
<input type="hidden" id="b2sInsertImageType" value="0">
<input type="hidden" id="isOgMetaChecked" value="<?php echo (isset($metaSettings['og_active']) ? (int) $metaSettings['og_active'] : 0); ?>">
<input type="hidden" id="isCardMetaChecked" value="<?php echo (isset($metaSettings['card_active']) ? (int) $metaSettings['card_active'] : 0); ?>">


<div id="b2s-post-ship-item-post-format-modal" class="modal fade" role="dialog" aria-labelledby="b2s-post-ship-item-post-format-modal" aria-hidden="true" data-backdrop="false" style="z-index: 1070">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="b2s-modal-close close" data-modal-name="#b2s-post-ship-item-post-format-modal">&times;</button>
                <h4 class="modal-title"><?php _e('Choose your', 'blog2social') ?> <span id="b2s-post-ship-item-post-format-network-title"></span> <?php _e('Post Format', 'blog2social') ?>
                    <?php if (B2S_PLUGIN_USER_VERSION >= 2) { ?>
                        <?php _e('for:', 'blog2social') ?> <span id="b2s-post-ship-item-post-format-network-display-name"></span>
                    <?php } ?>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12">
                        <?php
                        $settingsItem = new B2S_Settings_Item();
                        echo $settingsItem->setNetworkSettingsHtml();
                        echo $settingsItem->getNetworkSettingsHtml();
                        ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12">
                        <div class="text-center">
                            <br>
                            <div class="b2s-post-format-settings-info" data-network-id="1" style="display:none;">
                                <b><?php _e('Define the default settings for the custom post format for all of your Facebook accounts in the Blog2Social settings.', 'blog2social'); ?></b>
                            </div>
                            <div class="b2s-post-format-settings-info" data-network-id="2" style="display:none;">
                                <b><?php _e('Define the default settings for the custom post format for all of your Twitter accounts in the Blog2Social settings.', 'blog2social'); ?></b>
                            </div>
                            <div class="b2s-post-format-settings-info" data-network-id="10" style="display:none;">
                                <b><?php _e('Define the default settings for the custom post format for all of your Google+ accounts in the Blog2Social settings.', 'blog2social'); ?></b>
                            </div>
                            <div class="b2s-post-format-settings-info" data-network-id="12" style="display:none;">
                                <b><?php _e('Define the default settings for the custom post format for all of your Instagram accounts in the Blog2Social settings.', 'blog2social'); ?></b>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="b2s-network-select-image" class="modal fade" role="dialog" aria-labelledby="b2s-network-select-image" aria-hidden="true" data-backdrop="false" style="z-index: 1070">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="b2s-modal-close close" data-modal-name="#b2s-network-select-image">&times;</button>
                <h4 class="modal-title"><?php _e('Select image for', 'blog2social') ?> <span class="b2s-selected-network-for-image-info"></span></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="b2s-network-select-image-content"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<div id="b2s-show-post-all-modal" class="modal fade" role="dialog" aria-labelledby="b2s-post-all-modal" aria-hidden="true" data-backdrop="false" style="z-index: 1070">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="b2s-modal-close close" data-modal-name="#b2s-show-post-all-modal">&times;</button>
                <h4 class="modal-title"><?php _e('Select a post', 'blog2social') ?></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="b2s-all-post-content">
                            <div class="grid b2s-post">
                                <div class="grid-body">
                                    <div class="hidden-lg hidden-md hidden-sm filterShow"><a href="#" onclick="showFilter('show');return false;"><i class="glyphicon glyphicon-chevron-down"></i><?php _e('filter', 'blog2social') ?></a></div>
                                    <div class="hidden-lg hidden-md hidden-sm filterHide"><a href="#" onclick="showFilter('hide');return false;"><i class="glyphicon glyphicon-chevron-up"></i><?php _e('filter', 'blog2social') ?></a></div>
                                    <form class="b2sSortForm form-inline pull-left" action="#">
                                        <input id="b2sType" type="hidden" value="all" name="b2sType">
                                        <input id="b2sShowByDate" type="hidden" value="" name="b2sShowByDate">
                                        <input id="b2sPagination" type="hidden" value="1" name="b2sPagination">
                                        <?php
                                        $postFilter = new B2S_Post_Filter('all');
                                        echo $postFilter->getItemHtml();
                                        ?>
                                    </form>
                                </div>
                            </div>
                            <div class="clearfix"></div>
                            <div class="b2s-loading-area" style="display:none">
                                <br>
                                <div class="b2s-loader-impulse b2s-loader-impulse-md"></div>
                                <div class="clearfix"></div>
                                <div class="text-center b2s-loader-text"><?php _e("Loading...", "blog2social"); ?></div>
                            </div>
                            <div class="clearfix"></div>
                            <br>
                            <ul class="list-group b2s-sort-result-item-area"></ul>
                            <br>
                            <nav class="b2s-sort-pagination-area text-center"></nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<div id="b2s-sched-post-modal" class="modal fade" role="dialog" aria-labelledby="b2s-sched-post-modal" aria-hidden="true" data-backdrop="false" style="z-index: 1070">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="b2s-modal-close close" data-modal-name="#b2s-sched-post-modal">&times;</button>
                <h4 class="modal-title"><?php _e('Need to schedule your posts?', 'blog2social') ?></h4>
            </div>
            <div class="modal-body">
                <p><?php _e('Blog2Social Premium covers everything you need.', 'blog2social') ?></p>
                <br>
                <div class="clearfix"></div>
                <b><?php _e('Schedule post once', 'blog2social') ?></b>
                <p><?php _e('You want to publish a post on a specific date? No problem! Just enter your desired date and you are ready to go!', 'blog2social') ?></p>
                <br>
                <b><?php _e('Schedule post recurrently', 'blog2social') ?></b>
                <p><?php _e('You have evergreen content you want to re-share from time to time in your timeline? Schedule your evergreen content to be shared once, multiple times or recurringly at specific times.', 'blog2social') ?></p>
                <br>
                <b><?php _e('Best Time Scheduler', 'blog2social') ?></b>
                <p><?php _e('Whenever you publish a post, only a fraction of your followers will actually see your post. Use the Blog2Social Best Times Scheduler to share your post at the best times for each social network. Get more outreach and extend the lifespan of your posts.', 'blog2social') ?></p>
                <br>
                <?php if (B2S_PLUGIN_USER_VERSION == 0) { ?>
                    <hr>
                    <?php _e('With Blog2Social Premium you can:', 'blog2social') ?>
                    <br>
                    <br>
                    <span class="glyphicon glyphicon-ok glyphicon-success"></span> <?php _e('Post on pages and groups', 'blog2social') ?><br>
                    <span class="glyphicon glyphicon-ok glyphicon-success"></span> <?php _e('Share on multiple profiles, pages and groups', 'blog2social') ?><br>
                    <span class="glyphicon glyphicon-ok glyphicon-success"></span> <?php _e('Auto-post and auto-schedule new and updated blog posts', 'blog2social') ?><br>
                    <span class="glyphicon glyphicon-ok glyphicon-success"></span> <?php _e('Schedule your posts at the best times on each network', 'blog2social') ?><br>
                    <span class="glyphicon glyphicon-ok glyphicon-success"></span> <?php _e('Best Time Manager: use predefined best time scheduler to auto-schedule your social media posts', 'blog2social') ?><br>
                    <span class="glyphicon glyphicon-ok glyphicon-success"></span> <?php _e('Schedule your post for one time, multiple times or recurrently', 'blog2social') ?><br>
                    <span class="glyphicon glyphicon-ok glyphicon-success"></span> <?php _e('Schedule and re-share old posts', 'blog2social') ?><br>
                    <span class="glyphicon glyphicon-ok glyphicon-success"></span> <?php _e('Select link format or image format for your posts', 'blog2social') ?><br>
                    <span class="glyphicon glyphicon-ok glyphicon-success"></span> <?php _e('Select individual images per post', 'blog2social') ?><br>
                    <span class="glyphicon glyphicon-ok glyphicon-success"></span> <?php _e('Reporting & calendar: keep track of your published and scheduled social media posts', 'blog2social') ?><br>
                    <br>
                    <a target="_blank" href="<?php echo B2S_Tools::getSupportLink('affiliate'); ?>" class="btn btn-success center-block"><?php _e('Upgrade to PREMIUM', 'blog2social') ?></a>
                    <br>
                    <center><?php _e('or <a href="http://service.blog2social.com/trial" target="_blank">start with free 30-days-trial of Blog2Social Premium</a> (no payment information needed)', 'blog2social') ?></center>
                <?php } ?>
            </div>
        </div>
    </div>
</div>