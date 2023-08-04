<?php
/*
Script Name: GPT Classification Script
*/

// Load WordPress environment
require_once(__DIR__ . '/wp-load.php');

// Enable error reporting and display errors on the screen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "started";

// Classify the post using the GPT API
function classify_post($post_id) {
    

        
    $classified=FALSE;
    $attempts=0;

    do{
        $attempts=$attempts+1;

        $post = get_post($post_id);
    $content = $post->post_content;
    $content = wp_trim_words($content, 1000, '');
    $title = $post->post_title;

    echo $content;
    echo $title;

    $prompt = "USER: 
    ONLY RESPOND WITH THE CLASSIFICATION. YOU ARE NOT TO RESPOND WITH ANYTHING ELSE. NO OTHER WORDS MAY BE INCLUDED IN YOUR REPLY. The classifications and their definitions/traits are separated by a '=>'. The classification label is to the left of the '=>'. ONLY REPLY WITH THE APPROPRIATE CLASSIFICATION LABEL.\n\n

    'HARD NEWS' => 'Acquisitions | “[COMPANY NAME] has acquired [COMPANY NAME]”. Legislation changes, municipal law. Right to Repair. Headline includes “BREAKING NEWS” (not always)',
    'SOFT NEWS' => 'OEM Procedure Updates. [COMPANY] raises $ for [CHARITY]. people on the move. recalls. [HEADLINE], says [COMPANY/PERSON]. Report analyses; …report says/reads the report..etc. Single quotes in the headline. OEM/automaker news. NHTSA, safety, crash test. Tuesday Ticker, EV/AV Report, Welcomes of the Week | Headlines used in weekly stories.',
    'WHIMSY' => 'Can You Believe This | Weekly Friday video. Usually tagged #whimsy on the back-end. More of a goofy tone, article on the shorter side. ',
    'PRODUCT' => 'Describes a product. Product or logo in the featured photo. Also featured on buyersguide.collisionrepairmag.com. ',
    'PROFILE' => 'Any article focusing on one person/company. Q&A format (not always). Discuss operations or tells a story of an individual’s career. Executive Vision, Shop Profile, Company Profile, Profile.',
    'SURVEY' => 'Anything with the main headline “Stand Up Speak Out. Some (newer) will have a Google Form attached; old ones linked to Constant Contact.”',
    'COLUMNS' => 'anything that starts with 'column by'',
    'EVENT' => '“[COMPANY NAME] held its/their ____”. Anything with a Flickr album attached. Car show/conference/national/event/golf tournament/gathered/meeting/CCIF',
    'EVENT NOTICE' => '“[COMPANY NAME] has announced _____”. Includes dates; words like announce, taking place, scheduled for, early bird rates…',

    /n/n
    Title: ".$title."/n
    Content: ".$content ;


    // Set up the API request to ChatGPT
    $apiKey = '***************************';
    $model = 'gpt-3.5-turbo';
    $data = array(
      'messages' => array(
        array('role' => 'user', 'content' => $prompt )
      ),
      'model' => $model
    );

    // Convert data to JSON string
    $dataString = json_encode($data);

    // Send a POST request to the OpenAI API
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Authorization: Bearer ' . $apiKey
    ));
  
  // Get the response from the API
  $response = curl_exec($ch);
  $responseData = json_decode($response, true);
  $answer = $responseData['choices'][0]['message']['content'];

  echo $response;
 var_dump($responseData);


    if (curl_errno($ch)) {
        $error_message = curl_error($ch);
        error_log('cURL Error: ' . $error_message);
        // Handle the error gracefully, e.g., show a notification or log the error for later investigation
        echo 'cURL Error: ' . $error_message;
        return;
    }

    // Check the API response status
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode === 200) {
        $result = json_decode($response, true);
   //$classification = $result['choices'][0]['text']; // Updated response data access
        // Update the post meta with the classification
        if($answer=="HARD NEWS" || $answer=="SOFT NEWS" || $answer=="WHIMSY" || $answer=="PRODUCT" || $answer=="PROFILE" || $answer=="COLUMNS" || $answer=="EVENT" || $answer=="EVENT NOTICE" || $answer=="SURVEY"){
            update_post_meta($post_id, 'gpt_classification', $answer);
            $classified=TRUE;
        }
        
        error_log($response);
    } else {
        // Handle non-200 response gracefully, e.g., show a notification or log the error for later investigation
        echo 'GPT API Error: HTTP ' . $httpCode . ', Response: ' . $response;
        error_log('GPT API Error: HTTP ' . $httpCode . ', Response: ' . $response);
    }

    curl_close($ch);

    }while($classified==FALSE && $attempts <10);    
}

//Check if the post has already been classified or classified as null
function wasClassified($post_id){
    // Check if the post has already been classified
    $existing_classification = get_post_meta($post_id, 'gpt_classification', true);
    
    if (!empty($existing_classification)) {
       // Post has already been classified
       if(is_null($existing_classification)){
        return FALSE;
       }else{
        return TRUE;
       }       
    }else{return FALSE;}
}

// Classify all posts
function classify_all_posts() {
    $posts_per_batch = 100; // Set the desired number of posts per batch
    $paged = 1;

    // Set the start and end dates for the time period
    $start_date = '2022-01-01';
    $end_date = '2023-12-31';

    do {
        $args = array(
            'post_type' => 'post',
            'posts_per_page' => $posts_per_batch,
            'paged' => $paged,
            'date_query' => array(
                'after' => $start_date,
                'before' => $end_date,
                'inclusive' => true,
            ),
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $PostID=get_the_ID();
                if(!wasClassified($PostID)){
                    classify_post($PostID);
                    sleep(5);
                }

            }
        }

        wp_reset_postdata();

        $paged++;
    } while ($query->max_num_pages >= $paged);
}

// Run the classification for all posts
classify_all_posts();