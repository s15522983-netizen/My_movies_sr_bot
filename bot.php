<?php

$TOKEN = "8217295820:AAGx877Y27l_neiOgTvqKosj_y6s6-Q5VCY";
define('API_KEY', $TOKEN);

$MOVIES_FILE = "movies.json";
$TVSHOWS_FILE = "tvshows.json";
$ANIMES_FILE = "animes.json";
$ADMIN_ID = 337935813;
$ADMIN_ID2 = 5982730614;

$backup_id = -1003100557676;
$MOVIES_CHANNEL_ID = -1003148236922;
$TVSHOW_CHANNEL_ID = -1003136656819; // ⬅️ ADD YOUR TV SHOW CHANNEL ID
$ANIME_CHANNEL_ID = -1003165929741;  // ⬅️ ADD YOUR ANIME CHANNEL ID
$getmeJson = file_get_contents("https://api.telegram.org/bot{$TOKEN}/getMe");
$getme = json_decode($getmeJson, true);
$botUsername = $getme['result']['username'];

// File paths
$USERS_FILE = "users.txt";
$CHANNELS_FILE = "channels.txt";
$STATE_FILE = "admin_state.json";
$ALL_MOVIES_FILE = "allmovies.txt";
$ALL_SHOWS_FILE = "allshows.txt";
$ALL_ANIME_FILE = "allanime.txt";
$SETTINGS_FILE = "settings.json";

// Load settings (editable links)
function load_settings() {
    global $SETTINGS_FILE;
    $defaults = [
        'link_request'  => 'https://t.me/RAMOSS_4',
        'link_updates'  => 'https://t.me/oppn44',
        'link_movie'    => 't.me/oppn44'
    ];
    if (!file_exists($SETTINGS_FILE)) {
        file_put_contents($SETTINGS_FILE, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaults;
    }
    $saved = json_decode(file_get_contents($SETTINGS_FILE), true);
    return array_merge($defaults, is_array($saved) ? $saved : []);
}

function save_settings($settings) {
    global $SETTINGS_FILE;
    file_put_contents($SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$SETTINGS = load_settings();

// Load required channels
$channels = file_exists($CHANNELS_FILE) ? file($CHANNELS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

// Load state
function load_state()
{
    global $STATE_FILE;
    if (!file_exists($STATE_FILE))
        return [];
    return json_decode(file_get_contents($STATE_FILE), true) ?: [];
}

function save_state($state)
{
    global $STATE_FILE;
    file_put_contents($STATE_FILE, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Load data files
$MOVIES = [];
if (file_exists($MOVIES_FILE)) {
    $MOVIES = json_decode(file_get_contents($MOVIES_FILE), true) ?: [];
} else {
    file_put_contents($MOVIES_FILE, json_encode([]));
}

$TVSHOWS = [];
if (file_exists($TVSHOWS_FILE)) {
    $TVSHOWS = json_decode(file_get_contents($TVSHOWS_FILE), true) ?: [];
} else {
    file_put_contents($TVSHOWS_FILE, json_encode([]));
}

$ANIMES = [];
if (file_exists($ANIMES_FILE)) {
    $ANIMES = json_decode(file_get_contents($ANIMES_FILE), true) ?: [];
} else {
    file_put_contents($ANIMES_FILE, json_encode([]));
}

$ADMIN_STATE = load_state();

// Individual save functions
function sendBackupZip($botUsername, $backup_id, $date, $files, $zipName = 'backup.zip')
{
    // Create ZIP
    $zip = new ZipArchive();
    if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception("Failed to create ZIP file: $zipName");
    }

    // Add each file to the ZIP
    foreach ($files as $file) {
        if (file_exists($file)) {
            $zip->addFile($file, basename($file));
        } else {
            error_log("File not found: $file");
        }
    }

    $zip->close();

    // Send ZIP to Telegram
    bot("sendDocument", [
        "chat_id" => $backup_id,
        "caption" => "@$botUsername Backup Date: $date",
        "document" => new CURLFile($zipName)
    ]);

    // Optional: remove ZIP after sending
    if (file_exists($zipName)) {
        unlink($zipName);
    }
}
function save_movies()
{
    global $MOVIES_FILE, $MOVIES;
    file_put_contents($MOVIES_FILE, json_encode($MOVIES, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function save_tvshows()
{
    global $TVSHOWS_FILE, $TVSHOWS;
    file_put_contents($TVSHOWS_FILE, json_encode($TVSHOWS, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function save_animes()
{
    global $ANIMES_FILE, $ANIMES;
    file_put_contents($ANIMES_FILE, json_encode($ANIMES, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function save_admin_state()
{
    global $STATE_FILE, $ADMIN_STATE;
    file_put_contents($STATE_FILE, json_encode($ADMIN_STATE, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Helper function to send requests to Telegram API
function bot($method, $data = [])
{
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// Check if user joined all required channels
function check_user_subscription($user_id, $chat_id)
{
    global $channels;
    
    

    if (empty($channels))
        return true;

    $notJoined = [];
    foreach ($channels as $channel) {
        $join = @file_get_contents("https://api.telegram.org/bot" . API_KEY . "/getChatMember?chat_id=$channel&user_id=" . $user_id);

        if (
            strpos($join, '"status":"left"') !== false ||
            strpos($join, '"Bad Request: USER_ID_INVALID"') !== false ||
            strpos($join, '"status":"kicked"') !== false
        ) {
            $notJoined[] = $channel;
        }
    }

    if (!empty($notJoined)) {
        $buttons = [];
        foreach ($notJoined as $ch) {
            $buttons[] = [['text' => "اشترك في $ch", 'url' => "https://t.me/" . ltrim($ch, "@")]];
        }

        bot('sendMessage', [
            'chat_id' => $chat_id,
            'parse_mode' => 'Markdown',
            'text' => "－لتفعيل البوت، يجب عليك الاشتراك في جميع القنوات التالية:",
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]);
        return false;
    }
    return true;
}

// Pagination settings
define('ITEMS_PER_PAGE', 8);

// Handle incoming update
$update = json_decode(file_get_contents("php://input"), true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = isset($message['text']) ? trim($message['text']) : '';
    $user = isset($message['from']['username']) ? $message['from']['username'] : 'لا يوجد';
    $from_id = $message['from']['id'];

    // Get user profile photo - with error handling
    $geet = file_get_contents("https://api.telegram.org/bot" . API_KEY . "/getUserProfilePhotos?user_id=$from_id&limit=1");
    $json = json_decode($geet, true);

    // Check subscription
    if (!check_user_subscription($user_id, $chat_id)) {
        exit;
    }
    // Use default photo if no profile photo exists
    $photo = 'AgACAgIAAxkBAAKEv2jVBhrvK5-mEzimDOQm2hryKfNiAALV_DEbK3WoSr9S8pGyZBc1AQADAgADeAADNgQ'; // Add a default photo file_id here
    if (isset($json['result']['photos'][0][0]['file_id'])) {
        $photo = $json['result']['photos'][0][0]['file_id'];
    }

    $name = isset($message['from']['first_name']) ? $message['from']['first_name'] : 'مجهول';

    // Save users
    $users = file_exists($USERS_FILE) ? explode("\n", trim(file_get_contents($USERS_FILE))) : [];
    $users = array_filter($users); // Remove empty lines
    $userCount = count($users);

    if (!in_array($chat_id, $users)) {
        $caption = "تم دخول شخص جديد الى البوت 📬!n"
            . "📁 — الأسم : [" . $name . "](tg://user?id=" . $chat_id . ")\n"
            . "📁 — معرف الشخص : @" . $user . "\n"
            . "📁 — ايدي الشخص : `" . $from_id . "`\n"
            . "📁 — عدد المشتركين : " . ($userCount + 1);

        // Send notification to admin
        $response = bot('sendPhoto', [
            'chat_id' => $ADMIN_ID,
            'photo' => $photo,
            'caption' => $caption,
            'disable_web_page_preview' => true,
            'parse_mode' => "Markdown",
        ]);

        // Add new user and save
        $users[] = $chat_id;
        file_put_contents($USERS_FILE, implode("\n", $users) . "\n");
    }


    // Handle /start
    if ($text === "/start") {
        $name = $message['from']['first_name'];

        // Main menu inline keyboard
        $keyboard = [
            [
                ['text' => '🎬 الأفلام', 'callback_data' => 'category:movies'],
                ['text' => '📺 المسلسلات', 'callback_data' => 'category:tvshows']
            ],
            [
                ['text' => '🎞️ الأنمي', 'callback_data' => 'category:anime']
            ],
            [
                ['text' => '📥 لطلب عمل معين', 'url' => $SETTINGS['link_request']]
            ],
            [
                ['text' => '🔄 تحديثات البوت', 'url' => $SETTINGS['link_updates']]
            ]
        ];

        // Add admin panel button for admins
        if ($user_id == $ADMIN_ID || $user_id == $ADMIN_ID2) {
            $keyboard[] = [['text' => '👨‍💻 لوحة التحكم', 'callback_data' => 'admin_panel']];
        }

        bot("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "مرحباً $name بك في بوت راموس للأفلام\nاختر ما تريد من القائمة أدناه: 🎬🎞",
            "reply_markup" => json_encode(['inline_keyboard' => $keyboard])
        ]);
        exit;
    }

    // Handle commands
    switch ($text) {
        case '/movies':
            sendMoviesPage($chat_id, 0);
            exit;

        case '/tvshows':
            sendTvshowsPage($chat_id, 0);
            exit;

        case '/anime':
            sendAnimesPage($chat_id, 0);
            exit;
    }

    // Admin commands handling
    if (($user_id == $ADMIN_ID || $user_id == $ADMIN_ID2)) {
        // THEN, handle admin state for text messages
        if (isset($ADMIN_STATE[$user_id])) {
            handle_admin_state($message, $user_id, $chat_id);
            exit;
        }
    }

    // Handle text search for all users
    if ($text) {
        handle_text_search($text, $chat_id);
        exit;
    }
}


// MODIFIED: Greatly enhanced channel_post handler for all session-based uploads
if (isset($update['channel_post'])) {
    $message = $update['channel_post'];
    $channel_id = $message['chat']['id'];
    $channel_name = $message['chat']['name'];

    // Handle Movie Channel posts (no session needed)
    if ($channel_id == $MOVIES_CHANNEL_ID && isset($message['video']) && isset($message['caption'])) {
        $caption = trim($message['caption']);
        $movie_key = $caption;

        if (isset($MOVIES[$movie_key])) {
            bot("sendMessage", ["chat_id" => $ADMIN_ID, "text" => "الفيلم '$caption' موجود بالفعل, تم تجاهل المنشور"]);
        } else {
            $video_file_id = $message['video']['file_id'];
            file_put_contents($ALL_MOVIES_FILE, $movie_key . PHP_EOL, FILE_APPEND | LOCK_EX);
            $movie_link = $SETTINGS['link_movie'];
            $entry = ["video" => $video_file_id, "caption" => "[{$caption}]({$movie_link})"];
            $MOVIES[$movie_key] = [$entry];
            save_movies();
            bot("sendMessage", ["chat_id" => $ADMIN_ID, "text" => "✅ تم حفظ الفيلم من القناة $channel_name : '$caption'"]);
        }
        exit;
    }

    // Handle TV Show and Anime Channel posts (session-based)
    if (($channel_id == $TVSHOW_CHANNEL_ID || $channel_id == $ANIME_CHANNEL_ID) && isset($message['video'])) {
        $active_session_admin_id = null;
        $active_session_state = null;

        // Find an admin with an active upload session
        foreach ($ADMIN_STATE as $admin_id => $state) {
            if (isset($state['step']) && strpos($state['step'], 'awaiting_channel_upload') !== false) {
                $active_session_admin_id = $admin_id;
                $active_session_state = $state;
                break;
            }
        }

        if ($active_session_admin_id) {
            $video_file_id = $message['video']['file_id'];
            $name = $active_session_state['data']['name'];
            $season = $active_session_state['data']['season'];
            $action = $active_session_state['action'];

            $is_tv_show = (strpos($action, 'tv') !== false);
            $is_anime = (strpos($action, 'anime') !== false);

            // Verify post is in the correct channel for the action type
            if (($channel_id == $TVSHOW_CHANNEL_ID && !$is_tv_show) || ($channel_id == $ANIME_CHANNEL_ID && !$is_anime)) {
                bot("sendMessage", [
                    "chat_id" => $active_session_admin_id,
                    "text" => "خطأ ❌ تم نشر الفيديو في القناة الخاطئة!"
                ]);
                return;
            }

            // Determine if we're adding a specific episode or the next sequential one
            $is_specific_episode = ($active_session_state['step'] === 'awaiting_channel_upload_specific');

            if ($is_tv_show) {
                if (!isset($TVSHOWS[$name]))
                    file_put_contents($ALL_SHOWS_FILE, $name . PHP_EOL, FILE_APPEND | LOCK_EX);

                if ($is_specific_episode) {
                    $episode_num = $active_session_state['data']['episode'];
                    $TVSHOWS[$name][$season][$episode_num] = $video_file_id;
                    $date = date("Y-m-d H:i:s");
                    bot("sendMessage", ["chat_id" => $active_session_admin_id, "text" => "✅ تم حفظ حلقة المسلسل: {$name} S{$season} E{$episode_num}"]);
                    // IMPORTANT: Close the session after a single specific upload
                    unset($ADMIN_STATE[$active_session_admin_id]);
                    save_admin_state();
                    sendBackupZip(
                        $botUsername,
                        $backup_id,
                        $date,
                        ['tvshows.json', 'allshows.txt', 'animes.json', 'allanime.txt', 'movies.json', 'allmovies.txt', 'bot.php', 'channnels.txt', 'users.txt', 'debug.txt', 'admin_state.json'] // list of files to include
                    );
                } else { // Sequential upload
                    $ep_keys = isset($TVSHOWS[$name][$season]) ? array_keys($TVSHOWS[$name][$season]) : [];
                    $next_ep = empty($ep_keys) ? 1 : max($ep_keys) + 1;
                    $TVSHOWS[$name][$season][$next_ep] = $video_file_id;
                    bot("sendMessage", ["chat_id" => $active_session_admin_id, "text" => "✅ تم حفظ حلقة المسلسل: {$name} S{$season} E{$next_ep}"]);
                }
                save_tvshows();

            } elseif ($is_anime) {
                if (!isset($ANIMES[$name]))
                    file_put_contents($ALL_ANIME_FILE, $name . PHP_EOL, FILE_APPEND | LOCK_EX);

                if ($is_specific_episode) {
                    $episode_num = $active_session_state['data']['episode'];
                    $ANIMES[$name][$season][$episode_num] = $video_file_id;
                    $date = date("Y-m-d H:i:s");
                    bot("sendMessage", ["chat_id" => $active_session_admin_id, "text" => "✅ تم حفظ حلقة الانمي: {$name} S{$season} E{$episode_num}"]);
                    // IMPORTANT: Close the session after a single specific upload
                    unset($ADMIN_STATE[$active_session_admin_id]);
                    save_admin_state();
                    sendBackupZip(
                        $botUsername,
                        $backup_id,
                        $date,
                        ['tvshows.json', 'allshows.txt', 'animes.json', 'allanime.txt', 'movies.json', 'allmovies.txt', 'bot.php', 'channnels.txt', 'users.txt', 'debug.txt', 'admin_state.json'] // list of files to include
                    );
                } else { // Sequential upload
                    $ep_keys = isset($ANIMES[$name][$season]) ? array_keys($ANIMES[$name][$season]) : [];
                    $next_ep = empty($ep_keys) ? 1 : max($ep_keys) + 1;
                    $ANIMES[$name][$season][$next_ep] = $video_file_id;
                    bot("sendMessage", ["chat_id" => $active_session_admin_id, "text" => "✅ تم حفظ حلقة الانمي: {$name} S{$season} E{$next_ep}"]);
                }
                save_animes();
            }
        }
    }
    exit;
}


// Handle callback queries
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $data = $callback['data'];
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $callback_id = $callback['id'];
    $user_id = $callback['from']['id'];

    // Answer callback query
    bot('answerCallbackQuery', [
        'callback_query_id' => $callback_id
    ]);

    // Handle category selection
    if (strpos($data, 'category:') === 0) {
        $category = str_replace('category:', '', $data);

        switch ($category) {
            case 'movies':
                sendMoviesPage($chat_id, 0);
                break;
            case 'tvshows':
                sendTvshowsPage($chat_id, 0);
                break;
            case 'anime':
                sendAnimesPage($chat_id, 0);
                break;
        }
        exit;
    }

    // Handle pagination
    if (strpos($data, 'page:') === 0) {
        $parts = explode(':', $data);
        $type = $parts[1];
        $page = intval($parts[2]);

        switch ($type) {
            case 'movies':
                editMoviesPage($chat_id, $message_id, $page);
                break;
            case 'tvshows':
                editTvshowsPage($chat_id, $message_id, $page);
                break;
            case 'anime':
                editAnimesPage($chat_id, $message_id, $page);
                break;
        }
        exit;
    }

    // Handle season selection
    if (strpos($data, 'season:') === 0) {
        $parts = explode(':', $data);
        $type = $parts[1];
        $name = $parts[2];
        $season = intval($parts[3]);

        // Change this line to use page 0
        send_episodes(($type === 'tv' ? $TVSHOWS : $ANIMES), $name, $season, $chat_id, $type, 0);
        exit;
    }

    // Handle item selection
    if (strpos($data, 'select:') === 0) {
        $parts = explode(':', $data);
        $type = $parts[1];
        $name = $parts[2];

        if ($type === 'movie') {
            send_movie($name, $chat_id);
        } else {
            send_seasons($type, $name, $chat_id);
        }
        exit;
    }

    // Handle admin actions
    if (strpos($data, 'admin_') === 0 || $data === 'admin_panel') {
        handle_admin_callback($data, $user_id, $chat_id, $message_id);
        exit;
    }

    // Handle back to main menu
    if ($data === 'main_menu') {
        global $SETTINGS;
        $name = $update['callback_query']['from']['first_name'];

        // Main menu inline keyboard
        $keyboard = [
            [
                ['text' => '🎬 الأفلام', 'callback_data' => 'category:movies'],
                ['text' => '📺 المسلسلات', 'callback_data' => 'category:tvshows']
            ],
            [
                ['text' => '🎞️ الأنمي', 'callback_data' => 'category:anime']
            ],
            [
                ['text' => '📥 لطلب عمل معين', 'url' => $SETTINGS['link_request']]
            ],
            [
                ['text' => '🔄 تحديثات البوت', 'url' => $SETTINGS['link_updates']]
            ]
        ];

        // Add admin panel button for admins
        if ($user_id == $ADMIN_ID || $user_id == $ADMIN_ID2) {
            $keyboard[] = [['text' => '👨‍💻 لوحة التحكم', 'callback_data' => 'admin_panel']];
        }

        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            "text" => "مرحباً $name بك في بوت راموس للأفلام\nاختر ما تريد من القائمة أدناه: 🎬🎞",
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
        exit;
    }
    // Handle episode selection and pagination
    if (strpos($data, 'episode:') === 0) {
        $parts = explode(':', $data);
        $type = $parts[1];
        $name = $parts[2];
        $season = $parts[3];
        $episode_num = $parts[4];

        send_single_episode($type, $name, $season, $episode_num, $chat_id);
        exit;
    }

    // Handle episodes pagination
    if (strpos($data, 'episodes_page:') === 0) {
        $parts = explode(':', $data);
        $type = $parts[1];
        $name = $parts[2];
        $season = $parts[3];
        $page = intval($parts[4]);

        edit_episodes_page($chat_id, $message_id, $type, $name, $season, $page);
        exit;
    }
}

// Function to handle admin callback queries
function handle_admin_callback($data, $user_id, $chat_id, $message_id)
{
    global $ADMIN_STATE, $SETTINGS;

    if ($data === 'admin_panel') {
        $keyboard = [
            [
                ['text' => '🎬 إدارة الأفلام', 'callback_data' => 'admin_movies'],
                ['text' => '📺 إدارة المسلسلات', 'callback_data' => 'admin_tvshows']
            ],
            [
                ['text' => '🎞️ إدارة الأنمي', 'callback_data' => 'admin_anime'],
                ['text' => '📢 إدارة القنوات', 'callback_data' => 'admin_channels']
            ],
            [
                ['text' => '📣 اذاعة', 'callback_data' => 'admin_broadcast'],
                ['text' => '📊 إحصائيات', 'callback_data' => 'admin_stats']
            ],
            [
                ['text' => '⚙️ إعدادات الروابط', 'callback_data' => 'admin_links']
            ],
            [
                ['text' => '◀️ رجوع', 'callback_data' => 'main_menu']
            ]
        ];

        edit_message($chat_id, $message_id, '👨‍💻 لوحة تحكم الأدمن:', $keyboard);
        return;
    }

    switch ($data) {
        case 'admin_movies':
            $keyboard = [
                [
                    ['text' => '➕ إضافة فيلم', 'callback_data' => 'admin_add_movie'],
                    ['text' => '➖ حذف فيلم', 'callback_data' => 'admin_remove_movie']
                ],
                [
                    ['text' => '◀️ رجوع', 'callback_data' => 'admin_panel']
                ]
            ];

            edit_message($chat_id, $message_id, 'اختر إجراء لإدارة الأفلام:', $keyboard);
            break;

        case 'admin_tvshows':
            $keyboard = [
                [
                    ['text' => '➕ إضافة مسلسل', 'callback_data' => 'admin_add_tvshow'], // Adds a new show + season
                    ['text' => '➖ حذف مسلسل', 'callback_data' => 'admin_remove_tvshow']
                ],
                [
                    // MODIFIED: Renamed for clarity
                    ['text' => '➕ اضافة عدة حلقات', 'callback_data' => 'admin_add_episodes_tv'], // Adds multiple episodes to existing season
                    ['text' => '➕ اضافة حلقة برقم', 'callback_data' => 'admin_add_episode_by_number_tv'] // Adds one specific episode
                ],
                [
                    ['text' => '➖ حذف حلقة', 'callback_data' => 'admin_remove_episode']
                ],
                [
                    ['text' => '◀️ رجوع', 'callback_data' => 'admin_panel']
                ]
            ];

            edit_message($chat_id, $message_id, 'اختر إجراء لإدارة المسلسلات:', $keyboard);
            break;

        case 'admin_anime':
            $keyboard = [
                [
                    ['text' => '➕ إضافة انمي', 'callback_data' => 'admin_add_anime'],
                    ['text' => '➖ حذف انمي', 'callback_data' => 'admin_remove_anime']
                ],
                [
                    // MODIFIED: Renamed for clarity
                    ['text' => '➕ اضافة عدة حلقات', 'callback_data' => 'admin_add_episodes_anime'],
                    ['text' => '➕ اضافة حلقة برقم', 'callback_data' => 'admin_add_episode_by_number_anime']
                ],
                [
                    ['text' => '➖ حذف حلقة', 'callback_data' => 'admin_remove_episode_anime']
                ],
                [
                    ['text' => '◀️ رجوع', 'callback_data' => 'admin_panel']
                ]
            ];

            edit_message($chat_id, $message_id, 'اختر إجراء لإدارة الأنمي:', $keyboard);
            break;

        case 'admin_channels':
            $keyboard = [
                [
                    ['text' => '➕ إضافة قناة', 'callback_data' => 'admin_add_channel'],
                    ['text' => '➖ حذف قناة', 'callback_data' => 'admin_remove_channel']
                ],
                [
                    ['text' => '◀️ رجوع', 'callback_data' => 'admin_panel']
                ]
            ];

            edit_message($chat_id, $message_id, 'اختر إجراء لإدارة القنانات:', $keyboard);
            break;

        case 'admin_stats':
            $users = file_exists("users.txt") ? count(file("users.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
            $movies = file_exists("allmovies.txt") ? count(file("allmovies.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
            $shows = file_exists("allshows.txt") ? count(file("allshows.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
            $anime = file_exists("allanime.txt") ? count(file("allanime.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;

            $stats = "📊 إحصائيات البوت:\n\n";
            $stats .= "👥 عدد المستخدمين: $users\n";
            $stats .= "🎬 عدد الأفلام: $movies\n";
            $stats .= "📺 عدد المسلسلات: $shows\n";
            $stats .= "🎞️ عدد الانمي: $anime";

            $keyboard = [
                [['text' => '◀️ رجوع', 'callback_data' => 'admin_panel']]
            ];

            edit_message($chat_id, $message_id, $stats, $keyboard);
            break;

        case 'admin_broadcast':
            $ADMIN_STATE[$user_id] = [
                'action' => 'broadcast',
                'step' => 'awaiting_message'
            ];
            save_state($ADMIN_STATE);

            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => '📣 أرسل الآن الرسالة التي تريد إذاعتها لجميع المستخدمين.'
            ]);
            break;

        case 'admin_add_channel':
            $ADMIN_STATE[$user_id] = [
                'action' => 'add_channel',
                'step' => 'awaiting_channel'
            ];
            save_state($ADMIN_STATE);

            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => '📢 أرسل الآن اسم المستخدم للقناة (مثال: @mychannel)'
            ]);
            break;

        case 'admin_remove_channel':
            $ADMIN_STATE[$user_id] = [
                'action' => 'remove_channel',
                'step' => 'awaiting_channel'
            ];
            save_state($ADMIN_STATE);

            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => '📢 أرسل الآن اسم المستخدم للقناة (مثال: @mychannel)'
            ]);
            break;

        // MODIFIED: Combined logic for adding new shows/anime and adding episodes
        case 'admin_add_tvshow':
        case 'admin_add_anime':
        case 'admin_add_episodes_tv':
        case 'admin_add_episodes_anime':
            // Check if any other admin has an active upload session
            $session_active = false;
            foreach ($ADMIN_STATE as $id => $state) {
                if (isset($state['step']) && strpos($state['step'], 'awaiting_channel_upload') !== false) {
                    if ($id != $user_id) { // A session is active for someone else
                        $session_active = true;
                        break;
                    }
                }
            }

            if ($session_active) {
                bot('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => '⚠️ يوجد ادمن اخر يقوم بالتحميل, انتظر حتى ينتهي'
                ]);
                return;
            }

            $is_tv = in_array($data, ['admin_add_tvshow', 'admin_add_episodes_tv']);
            $type = $is_tv ? 'TV Show' : 'Anime';
            $action = $is_tv ? 'add_episodes_tv' : 'add_episodes_anime';

            $ADMIN_STATE[$user_id] = ['action' => $action, 'step' => 'ask_show_season'];
            save_admin_state();

            bot('sendMessage', [
                'chat_id' => $chat_id,
                'parse_mode' => 'Markdown',
                'text' => "📺 *جلسة تحميل للـ {$type}*\n\nأرسل الاسم والموسم بهذه الصيغة:\n`Name|Season`"
            ]);
            break;

        // NEW: Logic for adding a single episode by number
        case 'admin_add_episode_by_number_tv':
        case 'admin_add_episode_by_number_anime':
            // Session check
            $session_active = false;
            foreach ($ADMIN_STATE as $id => $state) {
                if (isset($state['step']) && strpos($state['step'], 'awaiting_channel_upload') !== false) {
                    if ($id != $user_id) {
                        $session_active = true;
                        break;
                    }
                }
            }
            if ($session_active) {
                bot('sendMessage', ['chat_id' => $chat_id, 'text' => '⚠️ يوجد ادمن اخر يقوم بالتحميل, انتظر حتى ينتهي']);
                return;
            }

            $is_tv = ($data === 'admin_add_episode_by_number_tv');
            $type = $is_tv ? 'TV Show' : 'Anime';
            $action = $is_tv ? 'add_specific_episode_tv' : 'add_specific_episode_anime'; // New action name

            $ADMIN_STATE[$user_id] = ['action' => $action, 'step' => 'ask_show_season_episode'];
            save_admin_state();

            bot('sendMessage', [
                'chat_id' => $chat_id,
                'parse_mode' => 'Markdown',
                'text' => "🔢 *اكتب تفاصيل حلقة الـ {$type}*\nnأرسل التفاصيل بهذه الصيغة:\n`الأسم|الموسم|الحلقة`\nمثال: Naruto|1|5"
            ]);
            break;

        case 'admin_remove_tvshow':
            $ADMIN_STATE[$user_id] = [
                'action' => 'remove_tvshow',
                'step' => 'ask_show_name'
            ];
            save_state($ADMIN_STATE);

            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => '📺 أرسل اسم المسلسل الذي تريد إزالته:'
            ]);
            break;

        case 'admin_remove_episode':
            $ADMIN_STATE[$user_id] = [
                'action' => 'remove_episode',
                'step' => 'awaiting_episode_details',
                'type' => 'tv'
            ];
            save_state($ADMIN_STATE);

            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "🎬 أرسل تفاصيل الحلقة بالصيغة التالية:\nاسم المسلسل|رقم الموسم|رقم الحلقة\n\nمثال:\nNaruto|2|5"
            ]);
            break;


        case 'admin_remove_anime':
            $ADMIN_STATE[$user_id] = [
                'action' => 'remove_anime',
                'step' => 'ask_anime_name'
            ];
            save_state($ADMIN_STATE);

            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => '📺 أرسل اسم الانمي الذي تريد إزالته:'
            ]);
            break;

        case 'admin_remove_episode_anime':
            $ADMIN_STATE[$user_id] = [
                'action' => 'remove_episode',
                'step' => 'awaiting_episode_details',
                'type' => 'anime'
            ];
            save_state($ADMIN_STATE);

            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "🎬 أرسل تفاصيل الحلقة بالصيغة التالية:\nاسم الانمي|رقم الموسم|رقم الحلقة\n\nمثال:\nNaruto|2|5"
            ]);
            break;

        case 'admin_add_movie':

            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => '🎬 لإضافة فيلم، أرسل الفيديو مع كتابة اسم الفيلم في الوصف (الكابشن)'
            ]);
            break;

        case 'admin_remove_movie':
            $ADMIN_STATE[$user_id] = [
                'action' => 'remove_movie',
                'step' => 'ask_movie_name'
            ];
            save_state($ADMIN_STATE);

            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => '🎬 أرسل اسم الفيلم الذي تريد إزالته:'
            ]);
            break;

        case 'admin_links':
            $current_request = $SETTINGS['link_request'];
            $current_updates = $SETTINGS['link_updates'];
            $current_movie   = $SETTINGS['link_movie'];
            $keyboard = [
                [
                    ['text' => '✏️ رابط لطلب عمل معين', 'callback_data' => 'admin_edit_link_request']
                ],
                [
                    ['text' => '✏️ رابط تحديثات البوت', 'callback_data' => 'admin_edit_link_updates']
                ],
                [
                    ['text' => '✏️ رابط كابشن الأفلام', 'callback_data' => 'admin_edit_link_movie']
                ],
                [
                    ['text' => '◀️ رجوع', 'callback_data' => 'admin_panel']
                ]
            ];
            $info_text = "⚙️ إعدادات الروابط الحالية:\n\n";
            $info_text .= "📥 لطلب عمل معين:\n{$current_request}\n\n";
            $info_text .= "🔄 تحديثات البوت:\n{$current_updates}\n\n";
            $info_text .= "🎬 رابط كابشن الأفلام:\n{$current_movie}";
            edit_message($chat_id, $message_id, $info_text, $keyboard);
            break;

        case 'admin_edit_link_request':
            $ADMIN_STATE[$user_id] = [
                'action' => 'edit_link',
                'step'   => 'awaiting_link',
                'key'    => 'link_request'
            ];
            save_state($ADMIN_STATE);
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text'    => "✏️ أرسل الرابط الجديد لزر (لطلب عمل معين):\n\nالرابط الحالي: " . $SETTINGS['link_request']
            ]);
            break;

        case 'admin_edit_link_updates':
            $ADMIN_STATE[$user_id] = [
                'action' => 'edit_link',
                'step'   => 'awaiting_link',
                'key'    => 'link_updates'
            ];
            save_state($ADMIN_STATE);
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text'    => "✏️ أرسل الرابط الجديد لزر (تحديثات البوت):\n\nالرابط الحالي: " . $SETTINGS['link_updates']
            ]);
            break;

        case 'admin_edit_link_movie':
            $ADMIN_STATE[$user_id] = [
                'action' => 'edit_link',
                'step'   => 'awaiting_link',
                'key'    => 'link_movie'
            ];
            save_state($ADMIN_STATE);
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text'    => "✏️ أرسل الرابط الجديد لكابشن الأفلام (يُكتب بدون https://):\n\nالرابط الحالي: " . $SETTINGS['link_movie'] . "\n\nمثال: t.me/mychannel"
            ]);
            break;
    }
}

// Function to handle admin state
function handle_admin_state($message, $user_id, $chat_id)
{
    global $ADMIN_STATE, $MOVIES, $TVSHOWS, $ANIMES, $channels, $backup_id, $botUsername, $SETTINGS, $MOVIES_FILE;

    $text = isset($message['text']) ? trim($message['text']) : '';
    if (empty($ADMIN_STATE[$user_id]))
        return; // Exit if no state
    $state = $ADMIN_STATE[$user_id];

    // Handle the /done command to end a sequential upload session
    $is_sequential_upload = in_array($state['action'], ['add_episodes_tv', 'add_episodes_anime']) && $state['step'] === 'awaiting_channel_upload';
    if ($text === '/done' && $is_sequential_upload) {
        $date = date("Y-m-d H:i:s");
        // decide which files to send
        if ($state['action'] === 'add_episodes_tv') {
            sendBackupZip(
                $botUsername,
                $backup_id,
                $date,
                ['tvshows.json', 'allshows.txt', 'animes.json', 'allanime.txt', 'movies.json', 'allmovies.txt', 'bot.php', 'channnels.txt', 'users.txt', 'debug.txt', 'admin_state.json'] // list of files to include
            );
        } elseif ($state['action'] === 'add_episodes_anime') {
            sendBackupZip(
                $botUsername,
                $backup_id,
                $date,
                ['tvshows.json', 'allshows.txt', 'animes.json', 'allanime.txt', 'movies.json', 'allmovies.txt', 'bot.php', 'channnels.txt', 'users.txt', 'debug.txt', 'admin_state.json'] // list of files to include
            );
        }
        unset($ADMIN_STATE[$user_id]);
        save_admin_state();
        bot("sendMessage", ["chat_id" => $chat_id, "text" => "✅ تم انتهاء جلسة التحميل."]);
        return;
    }
    switch ($state['action']) {
        case 'add_channel':
            if (strpos($text, "@") !== 0) {
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "⚠️ يجب أن يبدأ اسم القناة بـ @"
                ]);
                return;
            }

            $channels_file = "channels.txt";
            if (!file_exists($channels_file)) {
                file_put_contents($channels_file, "");
            }

            $channels_list = file($channels_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $channels_list = array_map('trim', $channels_list);

            if (in_array($text, $channels_list)) {
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "ℹ️ القناة موجودة بالفعل في القائمة."
                ]);
            } else {
                file_put_contents($channels_file, "\n$text" . PHP_EOL, FILE_APPEND | LOCK_EX);
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "✅ تمت إضافة القناة: $text"
                ]);
            }

            unset($ADMIN_STATE[$user_id]);
            save_state($ADMIN_STATE);
            break;

        case 'remove_channel':
            if (strpos($text, "@") !== 0) {
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "⚠️ يجب أن يبدأ اسم القناة بـ @"
                ]);
                return;
            }

            $channels_file = "channels.txt";
            if (!file_exists($channels_file)) {
                file_put_contents($channels_file, "");
            }

            $channels_list = file($channels_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $channels_list = array_map('trim', $channels_list);

            if (!in_array($text, $channels_list)) {
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "ℹ️ القناة غير موجودة بالفعل في القائمة."
                ]);
            } else {
                $channels_list = array_filter($channels_list, fn($ch) => $ch !== $text);
                file_put_contents($channels_file, implode(PHP_EOL, $channels_list) . PHP_EOL);
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "✅ تمت ازالة القناة: $text"
                ]);
            }

            unset($ADMIN_STATE[$user_id]);
            save_state($ADMIN_STATE);
            break;

        // MODIFIED: Combined logic for TV and Anime sequential uploads
        case 'add_episodes_tv':
        case 'add_episodes_anime':
            if ($state['step'] === 'ask_show_season') {
                $parts = explode('|', $text);
                if (count($parts) !== 2 || !is_numeric(trim($parts[1]))) {
                    bot("sendMessage", ["chat_id" => $chat_id, "text" => "⚠️ صيغة غير صحيحة. يرجى الكتابة بهذه الصيغة `Name|Season`."]);
                    return;
                }
                $name = trim($parts[0]);
                $season = trim($parts[1]);

                // NEW: Validation - check if the show/season exists when adding episodes
                $is_tv = ($state['action'] === 'add_episodes_tv');
                $data_source = $is_tv ? $TVSHOWS : $ANIMES;
                if (!isset($data_source[$name][$season])) {
                    // It's a new show/season, which is fine for this flow
                }

                $ADMIN_STATE[$user_id]['data'] = ['name' => $name, 'season' => $season];
                $ADMIN_STATE[$user_id]['step'] = 'awaiting_channel_upload';
                save_admin_state();

                $type = $is_tv ? "TV Show" : "Anime";
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "✅ المحتوى الخاص بـ *{$name} - Season {$season}*.\n\nاذهب الى قناة الـ {$type} وانشر الحلقات الآن.\n\nأضغط /done **هنا** عندما تنتهي.",
                    'parse_mode' => 'Markdown'
                ]);
            }
            break;

        // NEW: Logic for handling the specific episode upload state
        case 'add_specific_episode_tv':
        case 'add_specific_episode_anime':
            if ($state['step'] === 'ask_show_season_episode') {
                $parts = explode('|', $text);
                if (count($parts) !== 3 || !is_numeric(trim($parts[1])) || !is_numeric(trim($parts[2]))) {
                    bot("sendMessage", ["chat_id" => $chat_id, "text" => "⚠️ صيغة غير صحيحة. يرجى الكتابة بهذه الصيغة `Name|Season|Episode`."]);
                    return;
                }
                $name = trim($parts[0]);
                $season = trim($parts[1]);
                $episode = trim($parts[2]);

                $is_tv = ($state['action'] === 'add_specific_episode_tv');
                $data_source = $is_tv ? $TVSHOWS : $ANIMES;
                $type = $is_tv ? "TV Show" : "Anime";

                // Validation: Ensure the show and season exist before proceeding
                if (!isset($data_source[$name][$season])) {
                    bot("sendMessage", ["chat_id" => $chat_id, "text" => "⚠️ خطأ: هذا الـ {$type} والموسم غير وجود. يرجى أضافته اولا."]);
                    unset($ADMIN_STATE[$user_id]);
                    save_admin_state();
                    return;
                }

                $ADMIN_STATE[$user_id]['data'] = ['name' => $name, 'season' => $season, 'episode' => $episode];
                $ADMIN_STATE[$user_id]['step'] = 'awaiting_channel_upload_specific'; // A new, distinct step
                save_admin_state();

                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "✅ المحتوى الخاص بـ *{$name} - S{$season} E{$episode}*.\n\nاذهب الى قناة الـ {$type} وانشر الحلقة الخاصة الآن.",
                    'parse_mode' => 'Markdown'
                ]);
            }
            break;

        case 'remove_tvshow':
            $tvshows_file = "allshows.txt";
            $tvshows_json = "tvshows.json";

            if (!file_exists($tvshows_file)) {
                file_put_contents($tvshows_file, "");
            }

            $tvshows = file($tvshows_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $tvshows = array_map('trim', $tvshows);

            $removedFromTxt = false;
            if (in_array($text, $tvshows)) {
                $tvshows = array_filter($tvshows, fn($s) => $s !== $text);
                file_put_contents($tvshows_file, implode(PHP_EOL, $tvshows) . PHP_EOL, LOCK_EX);
                $removedFromTxt = true;
            }

            if (!file_exists($tvshows_json)) {
                file_put_contents($tvshows_json, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            $tvshows_data = json_decode(file_get_contents($tvshows_json), true);
            if (!is_array($tvshows_data))
                $tvshows_data = [];

            $removedFromJson = false;
            if (isset($tvshows_data[$text])) {
                unset($tvshows_data[$text]);
                file_put_contents($tvshows_json, json_encode($tvshows_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $removedFromJson = true;
            }

            if ($removedFromTxt || $removedFromJson) {
                $date = date("Y-m-d H:i:s");

                sendBackupZip(
                    $botUsername,
                    $backup_id,
                    $date,
                    ['tvshows.json', 'allshows.txt', 'animes.json', 'allanime.txt', 'movies.json', 'allmovies.txt', 'bot.php', 'channnels.txt', 'users.txt', 'debug.txt', 'admin_state.json'] // list of files to include
                );
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "✅ تم إزالة المسلسل: $text"
                ]);
            } else {
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "⚠️ المسلسل '$text' غير موجود في القائمة."
                ]);
            }

            unset($ADMIN_STATE[$user_id]);
            save_state($ADMIN_STATE);
            break;

        case 'remove_anime':
            $anime_file = "allanime.txt";
            $anime_json = "animes.json";

            if (!file_exists($anime_file)) {
                file_put_contents($anime_file, "");
            }

            $anime = file($anime_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $anime = array_map('trim', $anime);

            $removedFromTxt = false;
            if (in_array($text, $anime)) {
                $anime = array_filter($anime, fn($s) => $s !== $text);
                file_put_contents($anime_file, implode(PHP_EOL, $anime) . PHP_EOL, LOCK_EX);
                $removedFromTxt = true;
            }

            if (!file_exists($anime_json)) {
                file_put_contents($anime_json, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            $anime_data = json_decode(file_get_contents($anime_json), true);
            if (!is_array($anime_data))
                $anime_data = [];

            $removedFromJson = false;
            if (isset($anime_data[$text])) {
                unset($anime_data[$text]);
                file_put_contents($anime_json, json_encode($anime_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $removedFromJson = true;
            }

            if ($removedFromTxt || $removedFromJson) {
                $date = date("Y-m-d H:i:s");
                sendBackupZip(
                    $botUsername,
                    $backup_id,
                    $date,
                    ['tvshows.json', 'allshows.txt', 'animes.json', 'allanime.txt', 'movies.json', 'allmovies.txt', 'bot.php', 'channnels.txt', 'users.txt', 'debug.txt', 'admin_state.json'] // list of files to include
                );
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "✅ تم إزالة الانمي: $text"
                ]);
            } else {
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "⚠️ الانمي '$text' غير موجود في القائمة."
                ]);
            }

            unset($ADMIN_STATE[$user_id]);
            save_state($ADMIN_STATE);
            break;

        case 'remove_movie':
            $movies_file = "allmovies.txt";
            $movies_json = "movies.json";

            if (!file_exists($movies_file)) {
                file_put_contents($movies_file, "");
            }

            $movies = file($movies_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $movies = array_map('trim', $movies);

            $removedFromTxt = false;
            if (in_array($text, $movies)) {
                $movies = array_filter($movies, fn($s) => $s !== $text);
                file_put_contents($movies_file, implode(PHP_EOL, $movies) . PHP_EOL, LOCK_EX);
                $removedFromTxt = true;
            }

            if (!file_exists($movies_json)) {
                file_put_contents($movies_json, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            $movies_data = json_decode(file_get_contents($movies_json), true);
            if (!is_array($movies_data))
                $movies_data = [];

            $removedFromJson = false;
            if (isset($movies_data[$text])) {
                unset($movies_data[$text]);
                file_put_contents($movies_json, json_encode($movies_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $removedFromJson = true;
            }

            if ($removedFromTxt || $removedFromJson) {
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "✅ تم إزالة الفيلم: $text"
                ]);
            } else {
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "⚠️ الفيلم '$text' غير موجود في القائمة."
                ]);
            }

            unset($ADMIN_STATE[$user_id]);
            save_state($ADMIN_STATE);
            break;

        case 'broadcast':
            if ($state['step'] === 'awaiting_message' && !empty($text)) {
                // Clear the state immediately so admin can do other things
                unset($ADMIN_STATE[$user_id]);
                save_state($ADMIN_STATE);

                // Call the new broadcast handler function
                handle_broadcast($message, $chat_id);
            }
            break;

        case 'remove_episode':
            $parts = explode('|', $text);
            if (count($parts) !== 3) {
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "⚠️ صيغة غير صحيحة! يجب أن تكون:\nاسم المسلسل|رقم الموسم|رقم الحلقة"
                ]);
                return;
            }

            $showName = trim($parts[0]);
            $season = trim($parts[1]);
            $episodeNumber = (int) trim($parts[2]);

            $type = $state['type']; // 'tv' or 'anime'
            $success = false;

            if ($type === 'tv') {
                if (isset($TVSHOWS[$showName][$season][$episodeNumber])) {
                    unset($TVSHOWS[$showName][$season][$episodeNumber]);
                    save_tvshows();
                    $success = true;
                }
            } else { // Anime
                if (isset($ANIMES[$showName][$season][$episodeNumber])) {
                    unset($ANIMES[$showName][$season][$episodeNumber]);
                    save_animes();
                    $success = true;
                }
            }

            if ($success) {
                $date = date("Y-m-d H:i:s");
                if ($type === 'tv') {
                    sendBackupZip(
                        $botUsername,
                        $backup_id,
                        $date,
                        ['tvshows.json', 'allshows.txt', 'animes.json', 'allanime.txt', 'movies.json', 'allmovies.txt', 'bot.php', 'channnels.txt', 'users.txt', 'debug.txt', 'admin_state.json'] // list of files to include
                    );
                } else {
                    sendBackupZip(
                        $botUsername,
                        $backup_id,
                        $date,
                        ['tvshows.json', 'allshows.txt', 'animes.json', 'allanime.txt', 'movies.json', 'allmovies.txt', 'bot.php', 'channnels.txt', 'users.txt', 'debug.txt', 'admin_state.json'] // list of files to include
                    );
                }
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "✅ تم إزالة الحلقة رقم $episodeNumber من '$showName' الموسم $season."
                ]);
            } else {
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "⚠️ لم يتم العثور على الحلقة. تأكد من صحة الاسم والموسم ورقم الحلقة."
                ]);
            }

            // Clear the admin state
            unset($ADMIN_STATE[$user_id]);
            save_state($ADMIN_STATE);
            break;

        case 'edit_link':
            if ($state['step'] === 'awaiting_link') {
                $key = $state['key'];
                $new_link = trim($text);

                if (empty($new_link)) {
                    bot("sendMessage", [
                        "chat_id" => $chat_id,
                        "text"    => "⚠️ الرابط لا يمكن أن يكون فارغاً. أرسل الرابط مرة أخرى:"
                    ]);
                    return;
                }

                // If updating the movie caption link, replace ALL old links in movies.json
                // We work on the raw file text to avoid JSON-escaping mismatches (e.g. t.me\/oppn44)
                $updated_count = 0;
                if ($key === 'link_movie') {
                    $movies_raw = file_get_contents($MOVIES_FILE);
                    // Replace every Markdown link URL inside captions: [MovieName](OLD_URL) -> [MovieName](NEW_URL)
                    $movies_updated = preg_replace_callback(
                        '/(\[(?:[^\]]*)\]\()([^)]+)(\))/',
                        function ($m) use ($new_link, &$updated_count) {
                            $updated_count++;
                            return $m[1] . $new_link . $m[3];
                        },
                        $movies_raw
                    );
                    if ($updated_count > 0) {
                        file_put_contents($MOVIES_FILE, $movies_updated);
                        // Reload the in-memory array to stay in sync
                        $MOVIES = json_decode($movies_updated, true) ?: [];
                    }
                }

                $SETTINGS[$key] = $new_link;
                save_settings($SETTINGS);

                $key_labels = [
                    'link_request' => 'رابط زر (لطلب عمل معين)',
                    'link_updates' => 'رابط زر (تحديثات البوت)',
                    'link_movie'   => 'رابط كابشن الأفلام'
                ];
                $label = $key_labels[$key] ?? $key;

                $extra = ($key === 'link_movie' && $updated_count > 0)
                    ? "\n\n🎬 تم تحديث الرابط في {$updated_count} فيلم"
                    : "";

                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text"    => "✅ تم تحديث {$label} إلى:\n{$new_link}{$extra}"
                ]);

                unset($ADMIN_STATE[$user_id]);
                save_state($ADMIN_STATE);
            }
            break;
    }
}


/**
 * NEW: Handles broadcasting a message to all users in users.txt.
 * Includes success/failure counting and a delay between messages.
 */
function handle_broadcast($message, $admin_chat_id)
{
    global $USERS_FILE;

    // Check if the users file exists
    if (!file_exists($USERS_FILE)) {
        bot("sendMessage", [
            "chat_id" => $admin_chat_id,
            "text" => "⚠️ ملف المستخدمين (users.txt) غير موجود."
        ]);
        return;
    }

    $users = file($USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($users)) {
        bot("sendMessage", [
            "chat_id" => $admin_chat_id,
            "text" => "⚠️ لا يوجد مستخدمين في القائمة للإذاعة لهم."
        ]);
        return;
    }

    $text_to_send = $message['text'];
    $total_users = count($users);
    $success_count = 0;
    $fail_count = 0;

    // Notify admin that broadcast is starting
    bot("sendMessage", [
        "chat_id" => $admin_chat_id,
        "text" => "⏳ جاري بدء الإذاعة إلى $total_users مستخدم... سأعلمك عند الانتهاء."
    ]);

    // Loop through each user ID
    foreach ($users as $user_id) {
        $user_id = trim($user_id);
        if (empty($user_id))
            continue;

        // Send the message to the current user
        $response = bot('sendMessage', [
            'chat_id' => $user_id,
            'text' => $text_to_send,
            'parse_mode' => 'Markdown' // Optional: support Markdown in broadcasts
        ]);

        // Check the response from the Telegram API
        if (isset($response['ok']) && $response['ok']) {
            $success_count++;
        } else {
            $fail_count++;
        }

        // Wait for 0.3 seconds (300,000 microseconds) to avoid hitting rate limits
        usleep(300000);
    }

    // Send a final report to the admin
    $report = "📣 تم الانتهاء من الإذاعة. ✅\n\n";
    $report .= "- عدد الرسائل الناجحة: $success_count\n";
    $report .= "- عدد الرسائل الفاشلة: $fail_count\n";
    $report .= "- إجمالي المستخدمين: $total_users";

    bot("sendMessage", [
        "chat_id" => $admin_chat_id,
        "text" => $report
    ]);
}

/**
 * NEW: Handles text search for movies, TV shows, and anime.
 * For movies, it first tries an exact match. If none is found, it
 * searches for partial matches and shows them as options to the user.
 */
function handle_text_search($text, $chat_id)
{
    global $MOVIES, $TVSHOWS, $ANIMES;

    $text_lower = mb_strtolower(trim($text));

    // --- Step 1: Check for exact matches first (return immediately if found) ---

    // Movie exact match
    foreach ($MOVIES as $movie_key => $movie_data) {
        if (mb_strtolower($movie_key) === $text_lower) {
            send_movie($movie_key, $chat_id);
            return;
        }
    }

    // TV show exact match
    foreach ($TVSHOWS as $show_name => $seasons) {
        if (mb_strtolower($show_name) === $text_lower) {
            send_seasons('tv', $show_name, $chat_id);
            return;
        }
    }

    // Anime exact match
    foreach ($ANIMES as $anime_name => $seasons) {
        if (mb_strtolower($anime_name) === $text_lower) {
            send_seasons('anime', $anime_name, $chat_id);
            return;
        }
    }

    // --- Step 2: No exact match found, collect partial matches from ALL categories ---

    $found_any = false;

    // Movie partial matches
    $partial_movies = [];
    foreach ($MOVIES as $movie_key => $movie_data) {
        if (mb_stripos($movie_key, $text_lower) !== false) {
            $partial_movies[] = $movie_key;
        }
    }
    if (!empty($partial_movies)) {
        $found_any = true;
        $keyboard = buildPaginationKeyboard($partial_movies, 0, 'movies', 'movie');
        $keyboard['inline_keyboard'][] = [['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']];

        bot("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "🎬 نتائج بحث مقاربة للأفلام:",
            "reply_markup" => json_encode($keyboard)
        ]);
    }

    // TV show partial matches
    $partial_tv = [];
    foreach ($TVSHOWS as $show_name => $seasons) {
        if (mb_stripos($show_name, $text_lower) !== false) {
            $partial_tv[] = $show_name;
        }
    }
    if (!empty($partial_tv)) {
        $found_any = true;
        $keyboard = buildPaginationKeyboard($partial_tv, 0, 'tvshows', 'tv');
        $keyboard['inline_keyboard'][] = [['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']];

        bot("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "📺 نتائج بحث مقاربة للمسلسلات:",
            "reply_markup" => json_encode($keyboard)
        ]);
    }

    // Anime partial matches
    $partial_anime = [];
    foreach ($ANIMES as $anime_name => $seasons) {
        if (mb_stripos($anime_name, $text_lower) !== false) {
            $partial_anime[] = $anime_name;
        }
    }
    if (!empty($partial_anime)) {
        $found_any = true;
        $keyboard = buildPaginationKeyboard($partial_anime, 0, 'anime', 'anime');
        $keyboard['inline_keyboard'][] = [['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']];

        bot("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "🎞️ نتائج بحث مقاربة للأنمي:",
            "reply_markup" => json_encode($keyboard)
        ]);
    }

    // If something was found in any category, we're done
    if ($found_any) {
        return;
    }

    // --- Step 3: Nothing found at all ---
    $keyboard = [
        [['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']]
    ];

    bot("sendMessage", [
        "chat_id" => $chat_id,
        "text" => "تأكد من كتابة اسم الفيلم أو المسلسل بشكل صحيح!",
        "reply_markup" => json_encode(['inline_keyboard' => $keyboard])
    ]);
}

// Function to send seasons for a show
function send_seasons($type, $name, $chat_id)
{
    global $TVSHOWS, $ANIMES;

    $data = ($type === 'tv') ? $TVSHOWS : $ANIMES;

    if (!isset($data[$name])) {
        $keyboard = [
            [['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']]
        ];

        bot("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "⚠️ لا توجد مواسم متاحة لـ $name",
            "reply_markup" => json_encode(['inline_keyboard' => $keyboard])
        ]);
        return;
    }

    $buttons = [];
    foreach ($data[$name] as $season => $episodes) {
        $buttons[] = [
            ['text' => "الموسم $season", 'callback_data' => "season:{$type}:{$name}:{$season}"]
        ];
    }

    // Add back button
    $buttons[] = [['text' => '◀️ رجوع', 'callback_data' => "category:{$type}"]];

    bot("sendMessage", [
        "chat_id" => $chat_id,
        "text" => "اختر الموسم:",
        "reply_markup" => json_encode(['inline_keyboard' => $buttons])
    ]);
}

// NEW: Function to send episodes as paginated inline keyboard
function send_episodes($data, $name, $season, $chat_id, $type, $page = 0)
{
    if (!isset($data[$name][$season])) {
        $keyboard = [
            [['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']]
        ];

        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "لا توجد حلقات لهذا الموسم",
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
        return;
    }

    $episodes = $data[$name][$season];
    // Sort episodes by number (the key) to ensure correct order
    ksort($episodes, SORT_NUMERIC);

    $episodes_per_page = 25; // 5 rows × 5 columns = 25 episodes per page
    $episode_numbers = array_keys($episodes);
    $total_episodes = count($episode_numbers);
    $total_pages = ceil($total_episodes / $episodes_per_page);

    // Calculate start and end for current page
    $start = $page * $episodes_per_page;
    $end = min($start + $episodes_per_page, $total_episodes);

    // Create episode buttons (5 rows, 5 columns)
    $buttons = [];
    $current_row = [];

    for ($i = $start; $i < $end; $i++) {
        $episode_num = $episode_numbers[$i];
        $current_row[] = ['text' => "E$episode_num", 'callback_data' => "episode:{$type}:{$name}:{$season}:{$episode_num}"];

        // Create new row every 5 buttons (10 columns)
        if (count($current_row) >= 5) {
            $buttons[] = $current_row;
            $current_row = [];
        }
    }

    // Add any remaining buttons in the last row
    if (!empty($current_row)) {
        $buttons[] = $current_row;
    }

    // Add navigation buttons
    $nav_buttons = [];
    if ($page > 0) {
        $nav_buttons[] = ['text' => '⬅️ السابق', 'callback_data' => "episodes_page:{$type}:{$name}:{$season}:" . ($page - 1)];
    }
    if ($page < $total_pages - 1) {
        $nav_buttons[] = ['text' => 'التالي ➡️', 'callback_data' => "episodes_page:{$type}:{$name}:{$season}:" . ($page + 1)];
    }

    if (!empty($nav_buttons)) {
        $buttons[] = $nav_buttons;
    }

    // Add back button
    $buttons[] = [['text' => '◀️ رجوع للمواسم', 'callback_data' => "select:{$type}:{$name}"]];

    $message_text = "{$name} - الموسم {$season}\n";
    $message_text .= "الحلقات " . ($start + 1) . "-" . $end . " من " . $total_episodes . "\n";
    $message_text .= "اختر الحلقة:";

    bot("sendMessage", [
        "chat_id" => $chat_id,
        "text" => $message_text,
        "reply_markup" => json_encode(['inline_keyboard' => $buttons])
    ]);
}

// NEW: Function to handle episode pagination
function edit_episodes_page($chat_id, $message_id, $type, $name, $season, $page)
{
    global $TVSHOWS, $ANIMES;

    $data = ($type === 'tv') ? $TVSHOWS : $ANIMES;
    send_episodes($data, $name, $season, $chat_id, $type, $page);
}

// NEW: Function to send a single episode video
function send_single_episode($type, $name, $season, $episode_num, $chat_id)
{
    global $TVSHOWS, $ANIMES;

    $data = ($type === 'tv') ? $TVSHOWS : $ANIMES;

    if (isset($data[$name][$season][$episode_num])) {
        $video_id = $data[$name][$season][$episode_num];
        $type_text = ($type === 'tv') ? 'مسلسل' : 'انمي';

        bot("sendVideo", [
            "chat_id" => $chat_id,
            "video" => $video_id,
            "caption" => "{$name} - {$type_text}\nالموسم {$season} - الحلقة {$episode_num}",
        ]);
    } else {
        bot("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "⚠️ الحلقة غير موجودة"
        ]);
    }
}

/**
 * NEW: Sends a movie.
 * It now searches for a movie key that STARTS WITH the provided name.
 * This makes it compatible with potentially truncated names from callback buttons.
 */
function send_movie($name, $chat_id)
{
    global $MOVIES;

    $name_lower = mb_strtolower($name);
    $found_movie_data = null;

    // Search for a movie that STARTS WITH the given name to handle truncated callbacks
    foreach ($MOVIES as $movie_key => $movie_data) {
        if (strpos(mb_strtolower($movie_key), $name_lower) === 0) {
            $found_movie_data = $movie_data;
            break; // Found the first match, send it and stop searching
        }
    }

    if ($found_movie_data !== null) {
        foreach ($found_movie_data as $movie) {
            bot("sendVideo", [
                "chat_id" => $chat_id,
                "video" => $movie['video'],
                "caption" => $movie['caption'],
                "parse_mode" => "Markdown"
            ]);
        }

        // Add back button after sending movie
        $keyboard = [
            [['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']]
        ];

        bot("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "تم عرض الفيلم المطلوب ✅",
            "reply_markup" => json_encode(['inline_keyboard' => $keyboard])
        ]);
        return;
    }

    // If movie was not found
    $keyboard = [
        [['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']]
    ];

    bot("sendMessage", [
        "chat_id" => $chat_id,
        "text" => "⚠️ الفيلم غير موجود",
        "reply_markup" => json_encode(['inline_keyboard' => $keyboard])
    ]);
}

// Function to edit message
function edit_message($chat_id, $message_id, $text, $keyboard)
{
    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
    ]);
}

// ----------------- Pagination functions -----------------

function sendMoviesPage($chat_id, $page)
{
    $file = 'allmovies.txt';
    $movies = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

    if (empty($movies)) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => 'لا يوجد أفلام',
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']]]])
        ]);
        return;
    }

    $keyboard = buildPaginationKeyboard($movies, $page, 'movies', 'movie');
    $keyboard['inline_keyboard'][] = [['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']];

    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => 'اختر فيلم:',
        'reply_markup' => json_encode($keyboard)
    ]);
}

function editMoviesPage($chat_id, $message_id, $page)
{
    $file = 'allmovies.txt';
    $movies = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

    $keyboard = buildPaginationKeyboard($movies, $page, 'movies', 'movie');
    $keyboard['inline_keyboard'][] = [['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']];

    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => 'اختر فيلم:',
        'reply_markup' => json_encode($keyboard)
    ]);
}

function sendTvshowsPage($chat_id, $page)
{
    $file = 'allshows.txt';
    $tvshows = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

    if (empty($tvshows)) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => 'لا يوجد مسلسلات',
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']]]])
        ]);
        return;
    }
    $keyboard = buildPaginationKeyboard($tvshows, $page, 'tvshows', 'tv');
    $keyboard['inline_keyboard'][] = [['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']];

    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => 'اختر مسلسل:',
        'reply_markup' => json_encode($keyboard)
    ]);
}

function editTvshowsPage($chat_id, $message_id, $page)
{
    $file = 'allshows.txt';
    $tvshows = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

    $keyboard = buildPaginationKeyboard($tvshows, $page, 'tvshows', 'tv');
    $keyboard['inline_keyboard'][] = [['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']];

    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => 'اختر مسلسل:',
        'reply_markup' => json_encode($keyboard)
    ]);
}

function sendAnimesPage($chat_id, $page)
{
    $file = 'allanime.txt';
    $animes = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

    if (empty($animes)) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => 'لا يوجد انميات',
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']]]])
        ]);
        return;
    }
    $keyboard = buildPaginationKeyboard($animes, $page, 'anime', 'anime');
    $keyboard['inline_keyboard'][] = [['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']];

    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => 'اختر أنمي:',
        'reply_markup' => json_encode($keyboard)
    ]);
}

function editAnimesPage($chat_id, $message_id, $page)
{
    $file = 'allanime.txt';
    $animes = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

    $keyboard = buildPaginationKeyboard($animes, $page, 'anime', 'anime');
    $keyboard['inline_keyboard'][] = [['text' => '◀️ العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']];

    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => 'اختر أنمي:',
        'reply_markup' => json_encode($keyboard)
    ]);
}

function buildPaginationKeyboard(array $all_items, int $page, string $type, string $callback_type): array
{
    $total = count($all_items);
    $start = $page * ITEMS_PER_PAGE;
    $items_on_page = array_slice($all_items, $start, ITEMS_PER_PAGE);

    $inline_buttons = [];
    foreach ($items_on_page as $name) {
        // Use substr to keep callback data short, consistent with pagination
        $short = substr($name, 0, 60);
        $inline_buttons[] = [['text' => $name, 'callback_data' => "select:{$callback_type}:{$short}"]];
    }

    // Navigation buttons
    $nav = [];
    if ($page > 0) {
        $nav[] = ['text' => '⬅ السابق', 'callback_data' => "page:{$type}:" . ($page - 1)];
    }
    if ($start + ITEMS_PER_PAGE < $total) {
        $nav[] = ['text' => 'التالي ➡', 'callback_data' => "page:{$type}:" . ($page + 1)];
    }
    if (!empty($nav)) {
        $inline_buttons[] = $nav;
    }

    return ['inline_keyboard' => $inline_buttons];
}

?>