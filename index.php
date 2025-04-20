<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
include 'config.php';
include 'mail.php';
session_start();


// Display the message if it's set
if (isset($_SESSION['message'])) {
    echo '<div class="success-message">' . $_SESSION['message'] . 
         '<i class="fas fa-times" onclick="this.parentElement.remove();"></i></div>';
    unset($_SESSION['message']); // Clear the message after displaying it
}

date_default_timezone_set('Asia/Manila');

if(isset($_SESSION['user_id'])){
   $user_id = $_SESSION['user_id'];
}else{
   $user_id = '';
};

if (isset($_POST['register'])) {

    $name = $_POST['name'];
    $name = filter_var($name, FILTER_UNSAFE_RAW);
    $address = $_POST['address'];
    $address = filter_var($address, FILTER_UNSAFE_RAW);
    $contact_number = $_POST['contact_number'];
    $contact_number = filter_var($contact_number, FILTER_UNSAFE_RAW);
    $email = $_POST['email'];
    $email = filter_var($email, FILTER_UNSAFE_RAW);
    $pass = sha1($_POST['pass']);
    $pass = filter_var($pass, FILTER_UNSAFE_RAW);
    $cpass = sha1($_POST['cpass']);
    $cpass = filter_var($cpass, FILTER_UNSAFE_RAW);

    // Image Upload Handling
    $image_name = $_FILES['image_01']['name'];
    $image_name = filter_var($image_name, FILTER_SANITIZE_STRING);
    $image_tmp_name = $_FILES['image_01']['tmp_name'];
    $image_size = $_FILES['image_01']['size'];
    $image_folder = 'uploaded_images/' . $image_name;

    if ($image_size > 2000000) {
        $message[] = 'Image size is too large. Please upload an image smaller than 2MB.';
    } else {
        if (move_uploaded_file($image_tmp_name, $image_folder)) {
            // Check if user with the same email or username already exists
            $select_user = $conn->prepare("SELECT * FROM user WHERE name = ? OR email = ?");
            $select_user->execute([$name, $email]);

            if ($select_user->rowCount() > 0) {
                $message[] = 'Username or email already exists!';
            } else {
                if ($pass != $cpass) {
                    $message[] = 'Confirm password does not match!';
                } else {
                    // Insert user data along with the image into the user table
                    $insert_user = $conn->prepare("INSERT INTO user(name, address, contact_number, email, password, image) VALUES(?,?,?,?,?,?)");
                    $insert_user->execute([$name, $address, $contact_number, $email, $cpass, $image_name]);
                    $message[] = 'Registered successfully, login now please!';
                }
            }
        } else {
            $upload_error = $_FILES['image_01']['error'];
            $message[] = "Failed to upload image. Error code: $upload_error";
        }
    }
}


if(isset($_GET['logout'])){
   session_unset();
   session_destroy();
   header('location:index.php');
}

    $appointment = null;
    $pets = [];

    $select_appointmentsCount = $conn->prepare("SELECT COUNT(*) AS appointment_count FROM appointments WHERE user_id = ? AND status != 4");
    $select_appointmentsCount->execute([$user_id]);
    $appointmentsCount = $select_appointmentsCount->fetch(PDO::FETCH_ASSOC);

if (isset($_POST['submit_owner'])) {
   // Store owner details
   $owner_name = $_POST['name'];
   $address = $_POST['address'];
   $contact_number = $_POST['contact_number'];
   $email = $_POST['email'];
   $oname = $_POST['oname'];
   $cname = $_POST['cname'];
   $appointment_date = $_POST['appointment_date'];
   $appointment_time = $_POST['appointment_time']; // Capture the appointment time
   $num_pets = $_POST['num_pets'];

   // Store details in session
   $_SESSION['owner_details'] = [
       'user_id' => $user_id,
       'owner_name' => $owner_name,
       'address' => $address,
       'contact_number' => $contact_number,
       'email' => $email,
       'coowner_name' => $oname,
       'coowner_contact' => $cname,
       'appointment_date' => $appointment_date,
       'appointment_time' => $appointment_time, // Add time to session
       'num_pets' => $num_pets
        

   ];
}

// Check if owner details are set to display the pet form
$display_pet_form = isset($_SESSION['owner_details']);
$num_pets = $display_pet_form ? $_SESSION['owner_details']['num_pets'] : 0;


if(isset($_POST['submit_pets'])) {
    $owner_details = $_SESSION['owner_details']; // Ensure we use session data correctly

    // Validate that user_id is not empty
    if (empty($owner_details['user_id'])) {
        $message[] = 'User ID is missing. Please log in again.';
        header('Location: user_login.php');
        exit();
    }
    
    // Insert appointment details into the appointments table
    $insert_appointment = $conn->prepare("INSERT INTO appointments(user_id, owner_name, address, contact_number, email, coowner_name, coowner_contact, appointment_date, appointment_time, num_pets, status) VALUES(?,?,?,?,?,?,?,?,?,?, 1)");
    $insert_appointment->execute([
        $owner_details['user_id'],
        $owner_details['owner_name'],
        $owner_details['address'],
        $owner_details['contact_number'],
        $owner_details['email'],
        $owner_details['coowner_name'],
        $owner_details['coowner_contact'],
        $owner_details['appointment_date'],
        $owner_details['appointment_time'], 
        $num_pets
    ]);
    
    // Get the last inserted appointment ID
    $appointment_id = $conn->lastInsertId();
    
    for($i = 1; $i <= $num_pets; $i++) {
        $pet_name = $_POST["pet_name_$i"];
        $species = $_POST["species_$i"];
        $breed = $_POST["breed_$i"];
        $color = $_POST["color_$i"];
        $dob = $_POST["dob_$i"];
        $age = $_POST["age_$i"];
        $sex = $_POST["sex_$i"];
        $reg_no = $_POST["reg_no_$i"];
        $microchip_no = $_POST["microchip_no_$i"];
        $diet = $_POST["diet_$i"];
        $appetite = $_POST["appetite_$i"];
        $stool = $_POST["stool_$i"];
        $activity = $_POST["activity_$i"];
        $last_vet_visit = $_POST["last_vet_visit_$i"];
        $current_medications = $_POST["current_medications_$i"];
    
        // Calculate the total cost for this pet
        $services = $_POST["services_$i"] ?? [];
        $vaccines = $_POST["vaccines_$i"] ?? [];
        $mts = $_POST["mts_$i"] ?? [];
        $total_cost = 0;
    
        // Add the cost of selected services
        foreach ($services as $service) {
            $cost = explode('|', $service)[1];
            $total_cost += (int)$cost;
        }
    
        // Collect services and vaccines as strings
        $services_str = implode(', ', $services);
        $vaccines_str = implode(', ', $vaccines);
        $mts_str = implode(', ', $mts);
    
        // Add the cost of selected vaccines
        foreach ($vaccines as $vaccine) {
            $cost = explode('|', $vaccine)[1];
            $total_cost += (int)$cost;
        }

        foreach ($mts as $mts) {
            $cost = explode('|', $mts)[1];
            $total_cost += (int)$cost;
        }
    
        // Insert each pet's details into the pets table, including appointment_id
        $insert_pet = $conn->prepare("INSERT INTO pets(user_id, pet_name, species, breed, color, dob, age, sex, reg_no, microchip_no, diet, appetite, stool, activity, last_vet_visit, current_medications, services, vaccines, mts_str, total_cost, appointment_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $insert_pet->execute([$owner_details['user_id'], $pet_name, $species, $breed, $color, $dob, $age, $sex, $reg_no, $microchip_no, $diet, $appetite, $stool, $activity, $last_vet_visit, $current_medications, $services_str, $vaccines_str, $mts_str, $total_cost, $appointment_id]);
         
    }
    $email = $owner_details['email']; 
    $subject = 'Appointment Request Confirmation';
    $body = "Good Day Ma'am/Sir {$owner_details['owner_name']},<br><br>Thank you for requesting an appointment with Uptown Chiropractic. Please wait for our confirmation.";
    
    if (sendEmail($email, $subject, $body)) {
        $_SESSION['message'] = 'Your appointment request has been submitted successfully! Please check your email.';
    }

    // After inserting into the appointments table
    // Check if there is an existing entry for the selected date
     $appointment_date = $owner_details['appointment_date'];
     $fetch_slots = $conn->prepare("SELECT slots_remaining FROM appointment_slots WHERE date = ?");
     $fetch_slots->execute([$appointment_date]);
     $slots_data = $fetch_slots->fetch(PDO::FETCH_ASSOC);

if ($slots_data) {
    // Update the existing slot count
    $remaining_slots = $slots_data['slots_remaining'] - 1;

     // Check if the remaining slots are 0 to mark as fully booked
     if ($remaining_slots <= 0) {
        $remaining_slots = 0;
    }

    $update_slots = $conn->prepare("UPDATE appointment_slots SET slots_remaining = ? WHERE date = ?");
    $update_slots->execute([$remaining_slots, $appointment_date]);
} else {
    // Insert a new entry for the selected date with one less slot
    $remaining_slots = 9; // 10 - 1
    $insert_slots = $conn->prepare("INSERT INTO appointment_slots (date, slots_remaining) VALUES (?, ?)");
    $insert_slots->execute([$appointment_date, $remaining_slots]);
}


    // Clear the session data after successful submission
    unset($_SESSION['owner_details']);

      // Set a session variable to indicate success
      $_SESSION['submission_success'] = true;

    echo "<script>alert('Form submitted successfully!. Please check your email.');</script>";

    header('Location: index.php');
    exit();
}


function build_calendar($month, $year){
    global $conn; // Make sure $conn is available within the function

    $daysOfWeek = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
    $firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
    $numberDays = date('t', $firstDayOfMonth);
    $dateComponents = getdate($firstDayOfMonth);
    $monthName = $dateComponents['month'];
    $dayOfWeek = $dateComponents['wday'];
    $currentDate = date('Y-m-d');
    $currentTime = strtotime($currentDate);
    $next_month = date('m', mktime(0, 0, 0, $month + 1, 1, $year));
    $next_year = date('Y', mktime(0, 0, 0, $month + 1, 1, $year));

    $calendar = "<div class='calendar-header'>";
    $calendar .= "<h2>$monthName $year</h2>";
    $calendar .= "<div class='calendar-buttons'>";
    $calendar .= "<a class='btn' href='?month=" . date('m') . "&year=" . date('Y') . "#appoint' id='current-month-btn'>Current Month</a>";
    $calendar .= "<a class='btn' href='?month=" . $next_month . "&year=$next_year#appoint' id='next-month-btn'>Next Month</a>";
    $calendar .= "</div></div>";
    $calendar .= "<table class='calendar-table'><tr>";

    foreach ($daysOfWeek as $day) {
        $calendar .= "<th class='calendar-header-day'>$day</th>";
    }

    $calendar .= "</tr><tr>";

    if ($dayOfWeek > 0) {
        for ($k = 0; $k < $dayOfWeek; $k++) {
            $calendar .= "<td class='empty'></td>";
        }
    }

    $currentDay = 1;

    while ($currentDay <= $numberDays) {
        if ($dayOfWeek == 7) {
            $dayOfWeek = 0;
            $calendar .= "</tr><tr>";
        }

        $date = "$year-$month-" . str_pad($currentDay, 2, "0", STR_PAD_LEFT);
        $dateTime = strtotime($date);

        if ($dateTime < $currentTime) {
            $calendar .= "<td class='empty'></td>";
        } else {
            // Fetch the remaining slots for this date
            $fetch_slots = $conn->prepare("SELECT slots_remaining FROM appointment_slots WHERE date = ?");
            $fetch_slots->execute([$date]);
            $slots_data = $fetch_slots->fetch(PDO::FETCH_ASSOC);
            $slots_remaining = $slots_data ? $slots_data['slots_remaining'] : 10;

            if ($slots_remaining <= 0) {
                // Fully booked day
                $calendar .= "<td class='day fully-booked' rel='$date'>";
                $calendar .= "<strong>$currentDay</strong><br>";
                $calendar .= "<span class='full-booked-text'>Fully Booked</span>";
            }else{
            $calendar .= "<td class='day' rel='$date'>";
            $calendar .= "<strong>$currentDay</strong><br>";
            $calendar .= "<button class='slot-button' onclick='showForm(\"$date\")'>Slots: $slots_remaining</button>";
            
        }
        $calendar .= "</td>";
    }
        $currentDay++;
        $dayOfWeek++;
    }

    if ($dayOfWeek != 7) {
        $remainingDays = 7 - $dayOfWeek;
        for ($l = 0; $l < $remainingDays; $l++) {
            $calendar .= "<td class='empty'></td>";
        }
    }

    $calendar .= "</tr></table>";

    return $calendar;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>UptownChiropractic</title>

  
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="user.css">

   <!-- Include intl-tel-input CSS -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.css">

   <link rel="icon" type="image/x-icon" href="faviconn.ico">

</head>
<body>

<?php
if(isset($message)){
    foreach($message as $message){
         echo '
         <div class="message">
            <span>'.$message.'</span>
            <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
         </div>
         ';
      }
   }
?>

<header class="header">
   
<section class="fles">

<a href="index.php"><img src="img/cliniclogo.png" alt="" class="logoss"><h4></h4></a>

   <nav class="navbar">
      <a class="active" href="#home">Home</a>
      <a href="#about">About</a>
      <a href="#appoint">Appointment</a>
      <a href="#service">Services</a>
      <a href="#products">Feedbacks</a>
      <a href="contact.html">Contact</a>

   </nav>

   <div class="icons">
      <div id="menu-btn" class="fas fa-bars"></div>
      <div id="user-btn" class="fas fa-user"></div>
      <div id="request-btn" class="fa-solid fa-list">
      <?php if ($appointmentsCount['appointment_count'] > 0): ?>
        <span class="notification-badge" id="notif-badge"><?php echo $appointmentsCount['appointment_count']; ?></span> 
      <?php endif; ?>
      </div>
     
   </div>

</section>

</header>

<div class="user-account">

   <section>

      <div id="close-account"><span>Close</span></div>

     <div class="user">
        <?php
            $select_user = $conn->prepare("SELECT * FROM `user` WHERE id = ?");
            $select_user->execute([$user_id]);
            if ($select_user->rowCount() > 0) {
                while ($fetch_user = $select_user->fetch(PDO::FETCH_ASSOC)) {
                    $user_image = htmlspecialchars($fetch_user['image']); // Image filename from database
                    $user_image_url = 'uploaded_images/' . $user_image; // Full path to image
                    echo '<img src="' . $user_image_url . '" alt="User Image" class="profile-img">';
                    echo '<p>Welcome! <span>' . htmlspecialchars($fetch_user['name']) . '</span></p>';
                    echo '<a href="index.php?logout" class="btn">Logout</a>';
                }
            } else {
                echo '<p><span>You are not logged in now!</span></p>';
            }
        ?>
</div>

        <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="fles">

            <form action="user_login.php" method="post">
                <h3>Login now</h3>
                <div class="input-box"> 
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email" required class="box" placeholder="enter your email" maxlength="50">
                </div>
                <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="pass" required class="box" placeholder="enter your password" maxlength="20">
                </div>
                <input type="submit" value="login now" name="login" class="btn">
            </form>

            <form action="" method="post" enctype="multipart/form-data">
                <h3>Register now</h3>
                <div class="input-box">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="name" oninput="this.value = this.value.replace(/\s/g, '')" required class="box" placeholder="enter your username" maxlength="100">
                </div>
                <div class="input-box">
                <i class="fa-solid fa-location-dot"></i>
                <textarea name="address" required class="box" placeholder="enter your complete address" maxlength="255"></textarea>
                </div>
                <div class="input-box">
                <i class="fa-solid fa-phone"></i>
                <input type="tel" id="contact_number" name="contact_number" required class="box" placeholder="enter your contact number" maxlength="11">
                </div>
                <div class="input-box">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email" required class="box" placeholder="enter your active email" maxlength="50">
                </div>
                <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="pass" required class="box" placeholder="enter your password" maxlength="20" oninput="this.value = this.value.replace(/\s/g, '')">
                </div>
                <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="cpass" required class="box" placeholder="confirm your password" maxlength="20" oninput="this.value = this.value.replace(/\s/g, '')">
                </div>
                 <div class="input-box">
                 <i class=""></i>
                 <input type="file" name="image_01" required class="box" accept="image/jpg, image/jpeg, image/png, image/webp">
                 </div>
                <input type="submit" value="register now" name="register" class="btn">
            </form>

        </div>
        <?php endif;?>
   </section>

</div>

   </section>

</div>

 
<div class="my-request">
   <section>
      <div id="close-request"><span>Close</span></div>
     

      <!-- Appointment details will be displayed here -->
      <div id="appointment-details">
        <?php
        // Assuming you have established a PDO connection named $conn
        // and have a variable $user_id containing the user's ID

        if ($user_id != '') {
            // Fetch all appointments for the user
            $select_appointments = $conn->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC, appointment_time DESC");
            $select_appointments->execute([$user_id]);
            $appointments = $select_appointments->fetchAll(PDO::FETCH_ASSOC);

            // Check if there are any appointments
            if ($appointments): ?>
                <div class="appointment-summary" style="display: flex; flex-direction: column;">
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="appointment-summary" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h6 style="margin: 0; font-weight: bold; color: white;">
                                <?= htmlspecialchars($appointment['appointment_date']) ?> at <?= htmlspecialchars($appointment['appointment_time']) ?>
                            </h6>
                            <span style="margin-right: 10px; font-weight: bold;">
                                <?php
                                switch ($appointment['status']) {
                                    case 1:
                                        echo "<span style='color: orange;'>Waiting</span>";
                                        break;
                                    case 2:
                                        echo "<span style='color: lightblue;'>For Payment</span>";
                                        break;
                                    case 3:
                                        echo "<span style='color: green;'>Paid</span>";
                                        break;
                                    case 4:
                                        echo "<span style='color: yellow;'>For Process</span>";
                                        break;
                                    case 5:
                                        echo "<span style='color: red;'>Cancelled</span>";
                                        break;
                                    case 6:
                                        echo "<span style='color: green;'>Completed</span>";
                                        break;
                                    default:
                                        echo "<span>Status: Unknown</span>";
                                        break;
                                }
                                ?>
                            </span>
                            <button onclick="toggleDetails(<?= $appointment['appointment_id'] ?>)" style="padding: 8px 12px; border: none; border-radius: 5px; background-color: #007BFF; color: white; cursor: pointer; transition: background-color 0.3s;">
                                View Details
                            </button>
                        </div>

                        <div class="appointment-details" id="full-details-<?= $appointment['appointment_id'] ?>" style="display: none;">
                            <h5>Appointment Details</h5>
                            <p><strong>Patient Name:</strong> <?= htmlspecialchars($appointment['owner_name']) ?></p>
                            <p><strong>Address:</strong> <?= htmlspecialchars($appointment['address']) ?></p>
                            <p><strong>Contact Number:</strong> <?= htmlspecialchars($appointment['contact_number']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($appointment['email']) ?></p>
                            <p><strong>Accompany Name:</strong> <?= htmlspecialchars($appointment['coowner_name']) ?></p>
                            <p><strong>Accompany Contact:</strong> <?= htmlspecialchars($appointment['coowner_contact']) ?></p>
                            <p><strong>Appointment Date:</strong> <?= htmlspecialchars($appointment['appointment_date']) ?></p>
                            <p><strong>Time:</strong> <?= htmlspecialchars($appointment['appointment_time']) ?></p>
                            <p><strong>Number of Accompany:</strong> <?= htmlspecialchars($appointment['num_pets']) ?></p>

                            <?php
                            $grand_total = 0; // Initialize grand total for this appointment

                            // Fetch pets for the specific appointment
                            $select_pets = $conn->prepare("SELECT * FROM pets WHERE user_id = ? AND appointment_id = ?");
                            $select_pets->execute([$user_id, $appointment['appointment_id']]);
                            $pets = $select_pets->fetchAll(PDO::FETCH_ASSOC);
                             
                            foreach ($pets as $index => $pet): 
                                $grand_total += (float)$pet['total_cost']; 
                                $is_visible = $index === 0;
                            ?>
                            <div class="appointment-summary" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            </div>
                              <div class="pet-summary" style="margin-bottom: 10px;">
                                <h5>Your Details</h5>
                                <p><strong>Patient's Name:</strong> <?= htmlspecialchars($pet['pet_name']) ?></p>
                                <p><strong>Complain:</strong> <?= htmlspecialchars($pet['species']) ?></p>
                                <p><strong>Medical History:</strong> <?= htmlspecialchars($pet['breed']) ?></p>
                                <p><strong>Medication:</strong> <?= htmlspecialchars($pet['color']) ?></p>
                                <p><strong>Date of Birth:</strong> <?= htmlspecialchars($pet['dob']) ?></p>
                                <p><strong>Age:</strong> <?= htmlspecialchars($pet['age']) ?></p>
                                 <!-- "See More..." button -->
                                     <button id="see-more-<?= $appointment['appointment_id'] ?>-<?= $index ?>" onclick="showMoreDetails(<?= $appointment['appointment_id'] ?>, <?= $index ?>)" style="padding: 8px 12px; background-color: #007BFF; color: white; cursor: pointer; border: none; border-radius: 5px;">
                                       See More...
                                      </button>
                                <div class="pet-details" id="pet-details-<?= $appointment['appointment_id'] ?>-<?= $index ?>" style="display: none;">
                                <p><strong>Sex:</strong> <?= htmlspecialchars($pet['sex']) ?></p>
                                <p><strong>Last chiro visit/First time visit:</strong> <?= htmlspecialchars($pet['last_vet_visit']) ?></p>

                                <!-- Display services and vaccines -->
                                <p><strong>Services:</strong> <?= htmlspecialchars($pet['services']) ?></p>

                                <!-- Display total cost for each pet -->
                                <p><strong>Total Cost:</strong> ₱<?= htmlspecialchars($pet['total_cost']) ?></p>
                                
                              
                               </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <!-- Display grand total for the appointment -->
                            <p><strong>Grand Total for Appointment:</strong> ₱<?= number_format($grand_total, 2) ?></p>
                            

                            <?php if ($appointment['status'] == 6): // If status is "Completed" ?>       
                            
                            <?php
                                // Check if a review already exists for the appointment
                              $check_review = $conn->prepare("SELECT COUNT(*) FROM reviews WHERE appointment_id = ?");
                                  $check_review->execute([$appointment['appointment_id']]);
                                   $has_review = $check_review->fetchColumn() > 0;
                                           ?>

                                        <?php if (!$has_review): // Only show review form if no review exists ?>
                                        <div class="review-section">
                                            <h5>Give Your Feedback</h5>
                                            <form id="review-form-<?= htmlspecialchars($appointment['appointment_id']) ?>" 
                                            onsubmit="submitReview(event, <?= htmlspecialchars($appointment['appointment_id']) ?>)">
                                            <label for="rating">Rating:</label>
                                           <select id="rating-<?= htmlspecialchars($appointment['appointment_id']) ?>" name="rating" required>
                                              <option value="">Select</option>
                                              <option value="1">1 - Very Bad</option>
                                              <option value="2">2 - Bad</option>
                                              <option value="3">3 - Okay</option>
                                              <option value="4">4 - Good</option>
                                              <option value="5">5 - Excellent</option>
                                           </select>
                                         <br>
                                       <label for="feedback">Feedback:</label>
                                     <textarea id="feedback-<?= htmlspecialchars($appointment['appointment_id']) ?>" name="feedback" required></textarea>
                                    <br>
                                      <button type="submit" style="padding: 8px 12px; border-radius: 5px; background-color: #28a745; color: white; cursor: pointer;">
                                   Submit Review
                                   </button>
                                       </form>

                                        </div>
                                         <?php else: ?>
                                         <p><strong style= "font-size:2rem; color:white; text-align:center;">Thank you for submitting your feedback!</strong></p>
                                         <?php endif; ?>
                                    <?php endif; ?>


                            <?php if ($appointment['status'] == 2): // For Payment status ?>
                                <div class="payment-section">
                                    <h5>Payment Details</h5>
                                    <p><strong>Total Amount Due:</strong> ₱<?= number_format($grand_total, 2) ?></p>
                                    
                                    <input type="text" id="reference_no_<?= $appointment['appointment_id'] ?>" name="reference_no" placeholder="Enter reference number" style="margin: 5px 0; padding: 8px; border: 1px solid #ccc; border-radius: 5px; width: 100%;">

                                    <p><strong>Upload Receipt:</strong></p>
                                    <input type="file" id="receipt_<?= $appointment['appointment_id'] ?>" name="receipt" accept="image/*" style="margin: 5px 0; padding: 8px; border: 1px solid #ccc; border-radius: 5px; width: 100%;">

                                    <button onclick="submitPayment(<?= $appointment['appointment_id'] ?>)" style="margin-top: 10px; padding: 8px 12px; border: none; border-radius: 5px; background-color: #28a745; color: white; cursor: pointer;">
                                        Pay Now
                                    </button>
                                    <button onclick="submitCancel(<?= $appointment['appointment_id'] ?>)" 
                                            style="margin-top: 10px; padding: 8px 12px; border: none; border-radius: 5px; background-color: #FF0000; color: white; cursor: pointer;">
                                        Cancel Appointment 
                                    </button>

                                </div>
                            <?php endif; ?>
                            <?php if ($appointment['status'] == 1):?>
                                <button onclick="submitCancel(<?= $appointment['appointment_id'] ?>)" 
                                        style="margin-top: 10px; padding: 8px 12px; border: none; border-radius: 5px; background-color: #FF0000; color: white; cursor: pointer;">
                                    Cancel
                                </button>

                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <p class="found">No appointment request yet.</p>
            <?php endif; ?>

        <script>
            function toggleDetails(appointmentId) {
                let details = document.getElementById(`full-details-${appointmentId}`);
                details.style.display = details.style.display === "none" ? "block" : "none";
            }
            

            function submitPayment(appointmentId) {
                let referenceNo = document.getElementById(`reference_no_${appointmentId}`).value;
                let receipt = document.getElementById(`receipt_${appointmentId}`).files[0];

                if (!referenceNo || !receipt) {
                    alert("Please enter a reference number and upload the receipt.");
                    return;
                }

                let formData = new FormData();
                formData.append('appointment_id', appointmentId);
                formData.append('reference_no', referenceNo);
                formData.append('receipt', receipt);

                fetch('process_payment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Payment submitted successfully!");
                        location.reload();
                    } else {
                        alert("Payment submission failed. Please try again.");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("An error occurred. Please try again later.");
                });
            }

            function submitCancel(appointmentId) {
            if (confirm("Are you sure you want to cancel this appointment?")) {
                let formData = new FormData();
                formData.append('appointment_id', appointmentId);   

                fetch('cancel.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Appointment cancelled successfully!");
                        location.reload();
                    } else {
                        alert("Cancellation failed. Please try again.");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("An error occurred. Please try again later.");
                });
            }
        }
        

        </script>
        <?php } else { ?>
            <p class="found">User ID is not set.</p>
        <?php } // Check for user_id ?>
        
        <script>
        function toggleDetails(appointmentId) {
            const detailsDiv = document.getElementById(`full-details-${appointmentId}`);
            if (detailsDiv.style.display === "none" || detailsDiv.style.display === "") {
                detailsDiv.style.display = "block";
            } else {
                detailsDiv.style.display = "none";
            }
        }
        function showMoreDetails(appointmentId, index) {
    const petDetailsDiv = document.getElementById(`pet-details-${appointmentId}-${index}`);
    const seeMoreButton = document.getElementById(`see-more-${appointmentId}-${index}`);

    if (petDetailsDiv.style.display === "none" || petDetailsDiv.style.display === "") {
        petDetailsDiv.style.display = "block";
        seeMoreButton.textContent = "Hide Details..."; // Hide the "See More" button
    }else {
        petDetailsDiv.style.display = "none";
        seeMoreButton.textContent = "See More..."; // Change button text back to "See More"
    }
}

        </script>
        
        <script>
        function submitReview(event, appointmentId) {
    event.preventDefault();

    const rating = document.getElementById(`rating-${appointmentId}`).value;
    const feedback = document.getElementById(`feedback-${appointmentId}`).value;

    if (!rating || !feedback) {
        alert("Please provide both a rating and feedback.");
        return;
    }

    const formData = new FormData();
    formData.append('appointment_id', appointmentId);
    formData.append('rating', rating);
    formData.append('feedback', feedback);

    fetch('submit_review.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert("Review submitted successfully!");
            location.reload();
        } else {
            alert(`Failed to submit review: ${data.error || 'Unknown error'}`);
        }
    })
    .catch(error => {
        console.error("Error:", error);
        alert("An error occurred. Please try again later.");
    });
}

        </script>
        
        <script>
            function showMorePets(appointmentId) {
    // Get all hidden pet details for this appointment
    const hiddenPets = document.querySelectorAll(`#full-details-${appointmentId} .pet-details.hidden`);

    // Reveal each hidden pet and remove its hidden class
    hiddenPets.forEach((pet) => {
        pet.style.display = "block";
        pet.classList.remove("hidden");
    });

    // Hide the "See More" button after all pets are revealed
    const seeMoreBtn = document.getElementById(`see-more-btn-${appointmentId}`);
    if (seeMoreBtn) {
        seeMoreBtn.style.display = "none";
    }
}

        </script>
      </div>
   </section>
</div>


<div class="home-bg">

   
                <section class="home" id="home">

      <div class="slide-container">

         <div class="slide active">
            <div class="image">
               <img src="img/mucsles.jpg" alt="">
            </div>
            <div class="content">
               <h3>Muscle Re-Education</h3>
               <div class="fas fa-angle-left" onclick="prev()"></div>
               <div class="fas fa-angle-right" onclick="next()"></div>
            </div>
         </div>

         <div class="slide">
            <div class="image">
               <img src="img/brain.jpg" alt="">
            </div>
            <div class="content">
               <h3>Brain Frequency Training</h3>
               <div class="fas fa-angle-left" onclick="prev()"></div>
               <div class="fas fa-angle-right" onclick="next()"></div>
            </div>
         </div>

         <div class="slide">
            <div class="image">
               <img src="img/massage.jpg" alt="">
            </div>
            <div class="content">
               <h3>Massage Therapy</h3>
               <div class="fas fa-angle-left" onclick="prev()"></div>
               <div class="fas fa-angle-right" onclick="next()"></div>
            </div>
         </div>

         <div class="slide">
            <div class="image">
               <img src="img/cranial.jpg" alt="">
            </div>
            <div class="content">
               <h3>Cranial Bone Nerve Rehabilitation</h3>
               <div class="fas fa-angle-left" onclick="prev()"></div>
               <div class="fas fa-angle-right" onclick="next()"></div>
            </div>
         </div>

                 <div class="slide">
            <div class="image">
               <img src="img/spinal.jpg" alt="">
            </div>
            <div class="content">
               <h3>Spinal Decompression</h3>
               <div class="fas fa-angle-left" onclick="prev()"></div>
               <div class="fas fa-angle-right" onclick="next()"></div>
            </div>

         </div>

      </div>

   </section>

</div>

<section class="about" id="about">

   <h1 class="heading">about us</h1>

     <div class="box-container">

      <div class="box">
         <img src="img/care.jpg" alt="">
         <h3>Care with love</h3>
         <p>The doctor's expertise is in Chiropractic, Neurology, Structural Rehab, and Muscle Work. 

"Healing may feel good or bad, but it shouldn't take long to heal, if you're not getting better, you're getting worse" - Doc Nick
</p>
         <a href="#appoint" class="btn">appointment</a>
      </div>

      <div class="box">
         <img src="img/service.jpg" alt="">
         <h3>Reliable services</h3>
         <p>Doc Nick has treatment procedures that can benefit all types of patients. Whether you are physically active, non-active, young, old, or feeling good already, there is always something to improve on. Addressing the problem earlier will save you from suffering.</p>
         <a href="#appoint" class="btn">appointment</a>
      </div>

      <div class="box">
         <img src="img/mission.jpg" alt="">
         <h3>Our Mission</h3>
         <p>Our mission is to educate and adjust as many families as possible back toward optimal health through natural chiropractic care.</p>
         <a href="#appoint" class="btn">appointment</a>
      </div>

   </div>


</section>

<section class="show-contact" id="appoint">
   <h1 class="heading">Appointment</h1>
   <?php 
      $dateComponents = getdate();
      if(isset($_GET['month']) && isset($_GET['year'])){
         $month = $_GET['month']; 
         $year = $_GET['year'];
      }else{
         $month = $dateComponents['mon'];
         $year = $dateComponents['year'];
      }
      echo build_calendar($month, $year, $conn);
   ?>
</section>

<!-- Appointment Form Overlay -->
<div id='appointment-form' style='display:none;' class='modal'>
    <div class='modal-content'>
        <span class='close' onclick='closeForm()'>&times;</span>
        <form id='ownerForm' method="POST" action="submit_appointment.php">
            <h5>Patient's Form</h5>
            
            <?php if($user_id): ?>
                <?php
                // Fetch the logged-in user's details
                $select_user = $conn->prepare("SELECT * FROM user WHERE id = ?");
                $select_user->execute([$user_id]);
                $user = $select_user->fetch(PDO::FETCH_ASSOC);
                ?>

             
               <!-- Appointment date (non-editable) -->
                    <input type="text" name="appointment_date" id="appointment-date" readonly>
               <!-- Automatically fill the current time (non-editable) -->
                 
                   <input type="text" name="current_time" id="current-time" readonly class="box">
                
                   <select id="appointment-time" name="appointment_time" required class="box" onfocus="showTimeOptions();" onblur="checkEmpty(this);">
                    <option value="" disabled selected>Time</option> <!-- Placeholder option -->
                   </select>


                <!-- Pre-fill fields with user data -->
                <input type="text" name="name" value="<?= $user['name']; ?>" required class="box" placeholder="Patient's Name" maxlength="20" readonly>
                <input type="text" name="address" value="<?= $user['address']; ?>" required class="box" placeholder="Complete Address" maxlength="255" readonly>
                <input type="text" name="contact_number" value="<?= $user['contact_number']; ?>" required class="box" placeholder="Contact Number" maxlength="15" readonly>
                <input type="email" name="email" value="<?= $user['email']; ?>" required class="box" placeholder="Email Address" maxlength="50" readonly>

                <!-- Co-owner details -->
                <input type="text" name="oname" required class="box" placeholder="Accompany's Name" maxlength="20">
                <input type="text"  name="cname" required class="box" placeholder="Accompany's Contact Number" maxlength="11">
                
                <!-- Number of Pets -->
                <input type="number" name="num_pets" required class="box" placeholder="Number of Accompany" min="1" max="10">

                <input type="submit" value="Submit Now" name="submit" class="btn" onclick="return confirmSubmission();">
            
            <?php else: ?>
                <!-- If not logged in, display messages -->
                <p class="error-message">You must register and log in before proceeding with an appointment.</p>
                <a href="user_login.php" class="btn">Login</a>
                <a href="user_register." class="btn">Register</a>
            <?php endif; ?>
        </form>
    </div>
</div>


<?php if ($display_pet_form): ?>
<!-- Pet Details Form -->
<div id='pet-details-form' class='modal'>
    <div class='modal-content'>
        <form id="petForm" method="POST" action="">
        <span class='close' onclick='closeForm()'>&times;</span>
            <h3>Patient's Details</h3>

            <!-- Dynamically generated forms for each pet -->
            <?php for ($i = 1; $i <= $num_pets; $i++): ?>
                <div  id="pet-form-<?= $i ?>" style="display: <?= $i === 1 ? 'block' : 'none'; ?>;">
                    <h5>Pet <?= $i ?></h5>
                    <div style="display: flex; gap: 10px;">
                        <!-- Column 1 -->
                        <div style="flex: 1;">
                            <input type="text" name="pet_name_<?= $i ?>" required class="box" placeholder="Patient's Name">
                            <input type="text" name="species_<?= $i ?>" required class="box" placeholder="Complain">
                            <input type="text" name="breed_<?= $i ?>" required class="box" placeholder="Medical History">
                            <input type="text" name="color_<?= $i ?>" required class="box" placeholder="Medication">
                            <input type="date" name="dob_<?= $i ?>" required class="box date-input" placeholder="Date of Birth" onfocus="(this.type='date')" onblur="checkEmpty(this)">
                            <input type="number" name="age_<?= $i ?>" required class="box" placeholder="Age">
                            <select name="sex_<?= $i ?>" required class="box">
                             <option value="" disabled selected>Select Sex</option>
                             <option value="Male">Male</option>
                             <option value="Female">Female</option>
                            </select>

                        </div>
                        <!-- Column 2 -->
                        <div style="flex: 1;">
                            <input type="date" name="last_vet_visit_<?= $i ?>" class="box date-input" placeholder="Last chiro visit/Today if firstime" onfocus="(this.type='date')" onblur="checkEmpty(this)">
                        </div>
                    </div>
                     
                     <!-- Reason for Appointment/Service Selection -->
                    
                        <h4>Choose your treatment</h4>
                     <div>
                        <label>
                              <input type="checkbox" name="services_<?= $i ?>[]" value="Muscle Re-Education|4500" onchange="updateTotalCost()"> Muscle Re-Education (₱4500)
                        </label><br>

                        <label>
                                 <input type="checkbox" name="services_<?= $i ?>[]" value="Brain Frequency Training|5000" onchange="updateTotalCost()"> Brain Frequency Training (₱5000)
                        </label><br>
                        <label>
                                <input type="checkbox" name="services_<?= $i ?>[]" value="Massage Therapy|3500" onchange="updateTotalCost()"> Massage Therapy (₱3500)
                        </label><br>
                        <label>
                                 <input type="checkbox" name="services_<?= $i ?>[]" value="Cranial Bone Nerve Rehabilitation|4000" onchange="updateTotalCost()"> Cranial Bone Nerve Rehabilitation (₱4000)
                        </label><br>
                        <label>
                            <input type="checkbox" name="services_<?= $i ?>[]" value="Spinal Decompression|3500" onchange="updateTotalCost()"> Spinal Decompression (₱3500)

                        </label><br>
                            <label>
                            <input type="checkbox" name="services_<?= $i ?>[]" value="SStructural Rehab|2500" onchange="updateTotalCost()"> Structural Rehab (₱2500)
                        </label>


                    </div>
                    


                    <!-- Navigation Buttons -->
                    <?php if ($i > 1): ?>
                        <button type="button" class="btn" onclick="showPetForm(<?= $i - 1 ?>)">Back</button>
                    <?php endif; ?>

                    <?php if ($i < $num_pets): ?>
                        <button type="button" class="btn" onclick="validateAndShowNext(<?= $i ?>)">Next</button>
                    <?php else: ?>
                        <input type="submit" value="Submit" name="submit_pets" class="btn" onclick="return confirmSubmission();">
                         <!-- On the last form, display the total cost -->
                         <h4>Total Cost: ₱<span id="total_cost">0</span></h4>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function showForm(date) {

     // Check if the day is fully booked
     let selectedDay = document.querySelector(`.day[rel="${date}"]`);
    if (selectedDay.classList.contains('fully-booked')) {
        alert('This day is fully booked. Please choose another day.');
        return; // Exit the function if fully booked
    }

    // Check if user is logged in
    let userLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
    if (userLoggedIn) {
        // Display the appointment form
        const formOverlay = document.getElementById('appointment-form');
        formOverlay.style.display = 'block';
        
        // Pre-fill the appointment date in the form
        const dateInput = document.getElementById('appointment-date');
        dateInput.value = date;
        dateInput.setAttribute('readonly', true);

        // Automatically fill the current time
        const timeInput = document.getElementById('current-time');
        const now = new Date();
        const hours = now.getHours().toString().padStart(2, '0');
        const minutes = now.getMinutes().toString().padStart(2, '0');
        timeInput.value = `${hours}:${minutes}`;

        // Set up the time input
        setAvailableTimes(date);
    } else {
        // Show a message to log in or register
        alert('Please register and log in before proceeding.');
        window.location.href = 'index.php'; // Redirect to login page
    
    }
}
function showTimeOptions() {
    const timeInput = document.getElementById('appointment-time');
    
    // Check if the date is selected
    const appointmentDate = document.getElementById('appointment-date').value;
    
     
    if (appointmentDate) {
        // Clear previous options
        timeInput.innerHTML = '<option value="" disabled selected>Time to go</option>';
        // Generate available time slots for the selected date
        setAvailableTimes(appointmentDate);
    } else {
        // If no date is selected, prompt the user to choose a date
        alert('Please select a date first.');
    }
}
function setAvailableTimes(appointmentDate) {
    const timeInput = document.getElementById('appointment-time');
    const currentDate = new Date();
    const selectedDate = new Date(appointmentDate);

    // Get current time in Asia/Manila timezone
    const currentTime = new Date().toLocaleString('en-US', { timeZone: 'Asia/Manila' });
    const currentHour = new Date(currentTime).getHours();
    const currentMinutes = new Date(currentTime).getMinutes();

    // Define AM/PM period
    const period = (hour) => hour < 12 ? 'AM' : 'PM';

    // Convert 24-hour format to 12-hour format for display
    const to12HourFormat = (hour) => {
        const h = hour % 12;
        return h === 0 ? 12 : h;
    };

    // Determine time range based on the selected date
    let startHour, startMinutes;

    if (selectedDate.toDateString() === currentDate.toDateString()) {
        // If selected date is today, start from the current time
        startHour = currentHour;
        startMinutes = currentMinutes;
    } else {
        // If selected date is in the future, start from 1:00 AM
        startHour = 8;
        startMinutes = 0;
        endHour = 17;
    }

    // Generate time slots from the determined start time to 11:59 PM without 30-minute gaps
    for (let hour = startHour; hour <= endHour; hour++) {
        for (let minute = (hour === startHour ? startMinutes : 0); minute < 1; minute++) {
            const formattedHour = to12HourFormat(hour);
            const formattedMinute = String(minute).padStart(2, '0');
            const timePeriod = period(hour);
            const timeOption = `${formattedHour}:${formattedMinute} ${timePeriod}`;

            const optionElement = document.createElement('option');
            optionElement.value = timeOption;
            optionElement.textContent = timeOption;

            // Only show future times if today is selected
            if (selectedDate.toDateString() === currentDate.toDateString()) {
                if (hour > currentHour || (hour === currentHour && minute >= currentMinutes)) {
                    timeInput.appendChild(optionElement);
                }
            } else {
                timeInput.appendChild(optionElement);
            }
        }
    }
}

function checkEmpty(input) {
    // Reset input to the default placeholder if no option is selected
    if (input.value === '') {
        input.innerHTML = '<option value="" disabled selected>Time to go</option>';
    }
}   


function closeForm() {
    document.getElementById('appointment-form').style.display = 'none';
    document.getElementById('pet-details-form').style.display = 'none';
}

function showPetForm(index) {
    const numPets = <?= $num_pets; ?>;
    for (let i = 1; i <= numPets; i++) {
        document.getElementById('pet-form-' + i).style.display = i === index ? 'block' : 'none';
    }
}

function validateAndShowNext(index) {
    const currentForm = document.getElementById('pet-form-' + index);
    const inputs = currentForm.querySelectorAll('input[required]');
    let allFilled = true;

    inputs.forEach(input => {
        if (!input.value) {
            allFilled = false;
        }
    });

    if (allFilled) {
        showPetForm(index + 1); // Correct index to show the next form
    } else {
        alert('Please fill out all fields before proceeding.');
    }
}

function validateDateFormat(input) {
    const datePattern = /^\d{4}-\d{2}-\d{2}$/;  // Pattern for YYYY-MM-DD
    if (!input.value.match(datePattern)) {
        alert("Please enter the date in the correct format (YYYY-MM-DD).");
        input.focus();
    }
}

// Example of attaching the validation to the form's submission
document.getElementById('petForm').addEventListener('submit', function(e) {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => validateDateFormat(input));
});

function showConfirmation() {
    let confirmSubmission = confirm("Are you sure you want to submit?");
    if (confirmSubmission) {
        alert("Your appointment schedule request is successfully submitted.");
        document.querySelector('form').submit();
    }
}

document.getElementById('appointment-date').addEventListener('change', function() {
    const selectedDate = new Date(this.value);
    const currentDate = new Date();

    const timeInput = document.getElementById('appointment-time');

    if (selectedDate.toDateString() === currentDate.toDateString()) {
        // If the selected date is today, set the minimum time to the current time
        const currentHours = currentDate.getHours().toString().padStart(2, '0');
        const currentMinutes = currentDate.getMinutes().toString().padStart(2, '0');
        timeInput.min = `${currentHours}:${currentMinutes}`;
    } else {
        // If the selected date is in the future, allow any time from 00:00
        timeInput.min = '00:00';
    }

    // Update available times based on the selected date
    setAvailableTimes(this.value);
});


// Check if the input is empty, and if so, display the placeholder text
function checkEmpty(input) {
    if (input.value === '') {
        input.type = 'text';  // Reset input to text to show placeholder
    }
}

// Make sure the date input starts with 'text' type when empty
document.querySelectorAll('.date-input').forEach(function(input) {
    if (!input.value) {
        input.type = 'text';
    }
});

function toggleVaccineOptions(petIndex) {
    const vaccineCheckbox = document.querySelector(`[name="services_${petIndex}[]"][value="Vaccines|0"]`);
    const vaccineOptions = document.getElementById('vaccine-options-' + petIndex);

    // Show or hide vaccine options based on the "Vaccines" checkbox
    if (vaccineCheckbox.checked) {
        vaccineOptions.style.display = 'block';
    } else {
        vaccineOptions.style.display = 'none';
    }

    updateTotalCost();  // Update total cost when vaccines are toggled
}

function toggleMtsOptions(petIndex) {
    const mtsCheckbox = document.querySelector(`[name="services_${petIndex}[]"][value="Medical Test and Services|0"]`);
    const mtsOptions = document.getElementById('mts-options-' + petIndex);

    // Show or hide vaccine options based on the "Vaccines" checkbox
    if (mtsCheckbox.checked) {
        mtsOptions.style.display = 'block';
    } else {
        mtsOptions.style.display = 'none';
    }

    updateTotalCost();  // Update total cost when vaccines are toggled
}

function updateTotalCost() {
    let totalCost = 0;
    const numPets = <?= $num_pets; ?>;

    for (let i = 1; i <= numPets; i++) {
        const selectedServices = document.querySelectorAll(`[name="services_${i}[]"]:checked`);
        const selectedVaccines = document.querySelectorAll(`[name="vaccines_${i}[]"]:checked`);
        const selectedMts = document.querySelectorAll(`[name="mts_${i}[]"]:checked`);

        // Add the cost of selected services (e.g., Grooming, Deworming)
        selectedServices.forEach(service => {
            const price = parseInt(service.value.split('|')[1], 10);
            if (!isNaN(price)) {
                totalCost += price;
            }
        });

        // Add the cost of selected vaccines
        selectedVaccines.forEach(vaccine => {
            const price = parseInt(vaccine.value.split('|')[1], 10);
            if (!isNaN(price)) {
                totalCost += price;
            }
        });

        selectedMts.forEach(mts => {
            const price = parseInt(mts.value.split('|')[1], 10);
            if (!isNaN(price)) {
                totalCost += price;
            }
        });
    }

    // Update the total cost display
    document.getElementById('total_cost').textContent = totalCost;
}
     document.addEventListener('DOMContentLoaded', function() {
        const requestBtn = document.getElementById('request-btn');
        const notifBadge = document.getElementById('notif-badge');
        const myRequestSection = document.querySelector('.my-request');

        // Toggle the visibility of the request list and hide the notification badge
        requestBtn.addEventListener('click', function() {
            // Toggle the request section visibility
            if (myRequestSection.style.display === 'none' || myRequestSection.style.display === '') {
                myRequestSection.style.display = 'block';
            } else {
                myRequestSection.style.display = 'none';
            }

            // Hide the notification badge if it exists
            if (notifBadge) {
                notifBadge.style.display = 'none';
            }
        });
    });
</script>


<section class="service" id="service">
       <div class="wrapper">
            <h1 class="heading">Our Services</h1>
              <div class="content-box">
                   <div class="card">
                      <h2>Muscle Re-Education</h2>
                     <p>
                         It helps in correcting movement patterns and enhancing the body’s overall functionality. By focusing on the intricate relationship between the nervous system and muscles.
                     </p>
                     <a href="#appoint" class="cards-button">₱4500</a>

                   </div>

                   <div class="card">
                                         <h2>Brain Frequency Training</h2>
                     <p>
                        Is an alternative to addressing brain based issues without medication. Many people opt for this kind of therapy to avoid building a dependence on medication.
                     </p>
                     <a href="#appoint" class="cards-button">₱5000</a>
                   </div>

                   <div class="card">
                     <h2>Massage Therapy</h2>
                     <p>
                         Relax and relieve muscle tension with professional massage therapy designed for healing.
                     </p>
                     <a href="#appoint" class="cards-button">₱2500</a>
                   </div>


                   <div class="card">
                     <h2>Cranial Bone Nerve Rehabilitation</h2>
                     <p>
                         This technique is critical in addressing a range of health issues, from headaches and migraines to learning disorders, developmental delays, and trauma recovery.
                     </p>
                     <a href="#appoint" class="cards-button">₱4000</a>
                   </div>

                   <div class="card">
                     <h2>Spinal Decompression</h2>
                     <p>
                         Nonsurgical and surgical treatments designed to relieve pressure on the neural components of the spine.
                     </p>
                     <a href="#appoint" class="cards-button">₱3500</a>
                   </div>

                   <div class="card">
                     <h2>Structural Rehab</h2>
                     <p>
                     Aims to restore and improve the integrity of buildings and human bodies.
                     </p>
                     <a href="#appoint" class="cards-button">₱2500</a>
                   </div>


              </div>
     </div>
</section>


<h1 class="heading">Patient's Feedback</h1>
<section class="products" id="products">

<div class="cards-containers">
<div class="contents-box">
   <div class="cards">
      <img src="img/feedback1.jpg">
      <div class="cards-content">
        <h4>Maricris Navarro</h4>
        <p>Astounding presicion! Came in crying from pain on my 1st session and went out smiling with so much relief. I had gone thru a year with extreme headaches and a lot of oral medications which has led me to anxiety and depression. That 1st session with Doc Nick flipped everything. The relief I felt after the 1st therapy was an assurance that I will get well. I am currently on my 2nd package and every visit to the clinic is so worth it. Doc Nick knows exactly where it hurts without me saying a word. His ray hands (how i call it
e) works wonders and his expertise surprises me as he has given me instant relief and healing with every session.
        </p>
      </div>
    </div>
       <div class="cards">
      <img src="img/feedback2.jpg">
      <div class="cards-content">
        <h4>Martin Gamboa</h4>
        <p>One thing people might not know. Doc Nick is also trained in treating allergies.
        Doc Nick helped me with my allergic Rhinitis. It has lessened dramatically after he treated me for this
        This, aside from the other things he has helped me and continuing to do so for my well-being.More power Uptown Chiropractic - Spinal care center
        </p>
      </div>
    </div>
       <div class="cards">
      <img src="img/feedback3.jpg">
      <div class="cards-content">
        <h4>Maverick John Wick</h4>
        <p>I've been suffering from lower back pain so i decided to have it checked. What's surprising is Doc Nick even fixed my weak facial muscles. Its a big deal and a great relief for me because l've been playing the trumpet for more than a decade and been suffering from it. Thank you Uptown Chiropractic! Doc Nick is amazing, the staff are nice and friendly. You have my gratitude! * I can now do activities that i find taxing on my body.
        </p>

      </div>
    </div>
       <div class="cards">
      <img src="img/feedback4.jpg">
      <div class="cards-content">
        <h4>Rozel Abrantes</h4>
        <p>From May 16 to July 5 l am thankful to Doc Nik as my severe backache is now relived. For years I'm struggling with backache and the worst is I'm having numbness. With the treatment Thad with him it's superb. Thank you to Mrs. Fleming and the to their staff very accommodating and has a ready smile. The friendly atmosphere in the clinic. Now I'm back to work I felt lighter and no pain at all compared before. Thank you so much!
        </p>

      </div>
    </div>
        <div class="cards">
      <img src="img/feedback5.jpg">
      <div class="cards-content">
        <h4>Antonia Abad</h4>
        <p>The pain in my back, my migraine, in my knee disappeared. My eyes became clear. My diabetic foot used to be quite big, but it has shrunk. My feet are no longer numb, then the needling pain is gone. I feel relieved.
        </p>

      </div>
    </div>
       <div class="cards">
      <img src="img/feedback6.jpg">
      <div class="cards-content">
        <p>The Best Chiropractic-Spinal Center in Town... All my pains (Neck and Siatica) are gone, I can sleep better now thanks to you Doc Nick... you're the best!!!
Highly recommended.
        </p>

      </div>
    </div>
</div>
</div>
  


            </div>
           
            </div>

            </div>
            </div>

        </div>
</section>

<div class="credit">
   &copy; copyright @ <?= date('Y'); ?> by <span>Uptown Chiropractic</span> | All rights reserved!
</div>


<a href="#" id="back-to-top" title="Back to top" style="display: none;"><i class="fa fa-paper-plane-o" aria-hidden="true"></i></a>
         
<!-- custom js file link  -->
<script src="script.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js"></script>

<script>
const input = document.querySelector("#contact_number");
const iti = window.intlTelInput(input, {
   initialCountry: "ph",
   separateDialCode: true,
   onlyCountries: ["ph"],
});
</script>

    <script src="https://cdn.botpress.cloud/webchat/v2.2/inject.js"></script>
<script src="https://files.bpcontent.cloud/2025/03/31/02/20250331023209-KU2MKE82.js"></script>


</body>
</html>
