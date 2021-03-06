<?php
require('../model/database.php');
require('../model/user_db.php');
require ('../model/property_db.php');
require ('../model/category_db.php');
require ('../model/city_db.php');
require_once ('../util/main.php');
require_once ('../util/tags.php');
require_once ('../upload/file_util.php');  // the get_file_list function
require_once ('../upload/image_util.php'); // the process_image function

/**
 * Function processes files for upload
 * 1) checks if there are any files to upload
 * 2) loops through each image to move the original image into the image directory and processes derivatives of each image
 * 
 * returns array $images
**/
function upload_files() {
	$images = array();
	// check to make sure there are files
	if (isset($_FILES)) {
		// print_r($_FILES);
		// rearrange the files array to make it digestable
		$files = reArrayFiles($_FILES['file']);
		// print_r($files);
		// exit();
		// loop through the files
		foreach($files as $file) {
			// set the filename to the name of the file
			$filename = $file['name'];
			// if the filename is not empty
			if (!empty($filename)) {
				$source = $file['tmp_name'];
				$target = IMAGE_APP_DIR . $filename;
				move_uploaded_file($source, $target);
				
				// create the '400', '250', and '100' versions of the image
				$images[$filename] = process_image(IMAGE_APP_DIR, $filename);
			}		
		}		
	} else {
            // do nothing
	}	

	return $images;	
}

/**
 * Function reprocesses $_FILES array for easier handling
 * 
 * returns array $file_ary
**/
function reArrayFiles(&$file_post) {

    $file_ary = array();
    $file_count = count($file_post['name']);
    $file_keys = array_keys($file_post);

    for ($i=0; $i<$file_count; $i++) {
        foreach ($file_keys as $key) {
            $file_ary[$i][$key] = $file_post[$key][$i];
        }
    }	
    return $file_ary;
}

// Set session
if(!isset($_SESSION['user'])) {
    $lifetime = 60 * 60 * 2; // 2 hours session
    session_set_cookie_params($lifetime);
    session_start();
}

$action = filter_input(INPUT_POST, 'action');
if ($action == NULL) {
    $action = filter_input(INPUT_GET, 'action');
    if ($action == NULL) {
        if(isset($_SESSION['user'])) {
            $action = 'show_add_property_form';
        } else {
            $action = 'display_login';
        }
    }
}

// perform specific actions
switch ($action) {
    case 'display_login':
        include ('login.php');
        break;
    case 'login':
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $password = filter_input(INPUT_POST, 'password');
        if ($email == False || $email == NULL) {
            $message = 'Please use a valid email address.';
            include ('login.php');
        } else {
            $user = user_login($email, $password);
            if (!empty($user)) {
                $_SESSION['user'] = $user;
                $_SESSION['user']['password'] = $password;
//                $properties = get_property($propertyID);
                include 'profile.php';
            } else {
                $message = 'Sorry, either the username or password you entered was not valid. Please enter a valid
                            email and password.';
                include ('login.php');
            }
        }
        break;
    case 'show_create_form':
        $users = get_users();
        include('createaccount.php');
        break;
    case 'add_user':
        $name = filter_input(INPUT_POST, 'name');
        $lastname = filter_input(INPUT_POST, 'lastname');
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $password = filter_input(INPUT_POST, 'password');
        if ($name == NULL || $lastname == NULL || $email == FALSE || $email == NULL || $password == NULL) {
            $error = "Invalid data. Check all fields and try again.";
            include('../errors/error.php');
        } else {
            add_user($name, $lastname, $password, $email);
            include 'login.php';
        }
    case 'show_profile':
        if (isset($_SESSION['user'])) {
            $user = $_SESSION['user'];
            
            include ('profile.php');
        } else {
            include 'login.php';
        }
    break;
    case 'edit_profile_form':
        $users = get_users();
        include 'edit_profile_form.php';
        break;
    case 'edit_profile':
        $user_id = $_SESSION['user'];
        $name = filter_input(INPUT_POST, 'name');
        $lastname = filter_input(INPUT_POST, 'lastname');
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $password = filter_input(INPUT_POST, 'password');
        $phone = filter_input(INPUT_POST, 'phone');
        if ($user_id == NULL || $user_id == FALSE || $name == NULL || 
              $lastname == NULL || $email == NULL || $email == FALSE || $password == NULL || $phone == NULL) {
        $error = "Invalid product data. Check all fields and try again.";
        include('../errors/error.php');
        } else {
            edit_user($user_id['userID'], $name, $lastname, $password, $email, $phone);
           header("Location: ../account/?action=show_profile");
        }
        break;
    case 'show_add_property_form':
        if (isset($_SESSION['user'])) {
            $user = $_SESSION['user'];
            $categories = get_categories();
            $cities = get_cities();
            include ('add-property.php');
        } else {
            include 'login.php';
        }
        break;
    case 'add_property':
        $category_id = filter_input(INPUT_POST, 'categoryID', FILTER_VALIDATE_INT);
        $propertyName = filter_input(INPUT_POST, 'propertyName');
        $address = filter_input(INPUT_POST, 'address');
        $city_id = filter_input(INPUT_POST, 'adressID', FILTER_VALIDATE_INT);
        $state = filter_input(INPUT_POST, 'state');
        $zipCode = filter_input(INPUT_POST, 'zipCode');
        $propertyDetails = filter_input(INPUT_POST, 'propertyDetails');
        $numberOfBeds = filter_input(INPUT_POST, 'numberOfBeds', FILTER_VALIDATE_INT);
        $numberOfBaths = filter_input(INPUT_POST, 'numberOfBaths', FILTER_VALIDATE_INT);
        $propertyPhone = filter_input(INPUT_POST, 'propertyPhone');
        $propertyPrice = filter_input(INPUT_POST, 'propertyPrice', FILTER_VALIDATE_FLOAT);
        $propertySquareMeters = filter_input(INPUT_POST, 'propertySquareMeters', FILTER_VALIDATE_INT);
        $user_id = filter_input(INPUT_POST, 'userID', FILTER_VALIDATE_INT);
        
        // process images and serialize for storage
		$images = serialize(upload_files());
                
        if ($category_id == NULL || $category_id == false || $propertyName == NULL || $address == null || 
            $city_id == NULL || $city_id == false || $state == NULL || $zipCode == null || $zipCode == false || $propertyDetails == null 
            || $numberOfBeds == null || $numberOfBeds == false || $numberOfBaths == FALSE || $numberOfBaths == null
            || $propertyPhone == null || $propertyPrice == null || $propertyPrice == false || $propertySquareMeters == null
            || $propertySquareMeters == false || $user_id == null || $user_id == false) {
            $error = "Invalid data. Check all fields and try again.";
            include('../errors/error.php');
        } else {
            $categories = get_categories();
            $propertyID = add_property($category_id, $propertyName, $address, $city_id, $state, $zipCode, $numberOfBeds, $numberOfBaths, $propertyPrice, $propertySquareMeters, $propertyDetails, $propertyPhone, $user_id, $images);
            $property = get_property($property_id);
            include '../modules/property_view.php';
        }
        
        break;
    case 'logout':
        $_SESSION = array();
        session_destroy();
        $name = session_name();
        $expire = strtotime('-1 year');
        $params = session_get_cookie_params();
        $path = $params['path'];
        $domain = $params['domain'];
        $secure = $params['secure'];
        $httponly = $params['httponly'];
        setcookie($name, '', $expire, $path, $domain, $secure, $httponly);
        include 'login.php';
        break;
}

//echo '<pre>' . print_r($_SESSION, TRUE) . '</pre>';

?>

