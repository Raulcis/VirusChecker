<?php
    require_once 'login.php';

	$isLoggedIn = false;
	$username = "";
    $role = "";

	$conn = new mysqli($hn, $un, $pw, $db);

	if( isset($_POST['login_username']) )
	{
		// checks if login successful and sets cookie
		[$isLoggedIn, $username, $role] = loginUser($conn);
	}

    if( isset($_COOKIE['username']) )
	{
		$isLoggedIn = true;
		$username = $_COOKIE['username'];
        $role = $_COOKIE['role'];
	}

	if( isset($_POST['logoutBtn']) )
	{
		setcookie('username', "", time() - 2592000, '/');
		$isLoggedIn = false;
	}

	registerUser($conn);
	createAdmin($conn);

	echo <<<_END
	<pre>
	───▄▄▄
	─▄▀░▄░▀▄
	─█░█▄▀░█        Virus Checker - Collaborator Page
	─█░▀▄▄▀█▄█▄▀
	▄▄█▄▄▄▄███▀
	</pre>
	_END;

	displayBackButton();

	if( !$isLoggedIn )
	{
		displaySignInForm();
		displayRegisterForm();
		displayCreateAdminAccountButton();
	}
	else
	{
		displayLogout();
        echo "Logged in as user: '$username', role: '$role'<br/>";

		displayFileUploadForm($username);

		deleteAdminContent($conn);
		deleteCollaboratorContent($conn);
		movePutativeContent($conn);

		if(initialFileCheck())
		{
			$signature = getSignatureFromFileContents($_FILES['filename']['tmp_name']);
			$content_name = htmlentities( $_POST['content_name']);

			if($role === 'admin')
			{
				insert_admin_content($conn, $content_name, $signature);
			}
			else {
				insert_collaborator_content($conn, $username, $content_name, $signature);
			}
		}
		else if (isset($_POST['content_name'])){
			echo "<span style='color:red'>Upload Failed, make sure 'Content Name' only consists of Alphanumeric symbols without spaces.</span><br/>";
		}

		if ($role === 'admin')
		{
			//display admin content
			displayAdminContent($conn);
			displayCollaboratorContentForAdmin($conn);
		}
		else {
			displayCollaboratorContentForCollaborator($conn, $username);
		}
	}

	function displayBackButton()
	{
		echo <<<_END
		<form action="virus_app.php" method="post">
		<input type='submit' name='logoutBtn' value="Go Back">
		</form>
		_END;
	}

	function displayCreateAdminAccountButton()
	{
		echo "<hr/>";
		echo "<h2>For Grader Testing Purpose:</h2>Use this button to create an Admin Account under the username: 'admin' and password: 'password'.<br/>";
		echo <<<_END
		<form action="virus_login.php" method="post">
		<input type='submit' name='createAdminBtn' value="Create Admin Account">
		</form>
		_END;
	}

	function createAdmin($conn)
	{
		if ( isset($_POST['createAdminBtn']) )
		{
			$username = "admin";
			$email = "admin@gmail.com";
			$password = "password";
            $role = "admin";

			$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

			$query = "INSERT INTO users VALUES" .
			        "('$username', '$email', '$hashedPassword', '$role')";

			$result = $conn->query($query);
			
            if (!$result) 
			{
				echo "INSERT failed: $query<br>" . $conn->error . "<br><br>";
			}
			else 
            {
				echo "Registered user: '$username'<br/>";
				// $result->close();
				//you can't close this type of query object
				//because it post to database and does not contain
				//anything.
			}
		}
	}

	function getAllFileContents($filename)
	{
		$fh = fopen($filename, 'r') or -1;

		if($fh === -1) return "Could not access file!";

		$contents = fread($fh, filesize($filename));
		fclose($fh);

		return $contents;
	}

	// Grabs up to first 20 bytes
	function getSignatureFromFileContents($filename)
	{
		$filesize = $_FILES['filename']['size'];
		$fh = fopen($filename, 'r') or -1;

		if( $fh === -1 ) return "Could not access file!";

		if( $filesize <= 20 )
		{
			$signature = fread($fh, filesize($filename));
		}
		else
		{
			$signature = fread($fh, 20);
		}

		fclose($fh);

		return $signature;
	}

	function insert_admin_content($conn, $content_name, $file_signature)
	{
		$query = "INSERT INTO virus_information (virus_name, signature_20_bytes) VALUES" .
													"('$content_name', '$file_signature')";

		$result = $conn->query($query);
		if (!$result) 
		{
			echo "INSERT failed: $query<br/>" . $conn->error . "<br/><br/>";
		}
		// $result->close();
	}

	function insert_collaborator_content($conn, $username, $content_name, $file_signature)
	{
		$query = "INSERT INTO virus_putative_information (username, virus_name, signature_20_bytes) VALUES" .
													"('$username', '$content_name', '$file_signature')";

		$result = $conn->query($query);
		if (!$result) 
		{
			echo "INSERT failed: $query<br/>" . $conn->error . "<br/><br/>";
		}
		// $result->close();
	}

	function deleteAdminContent($conn)
	{
		if (isset($_POST['delete_admin_content']) && 
			isset($_POST['del_admin_content_id']) && 
			isset($_POST['del_admin_content_name']) && 
			isset($_POST['del_admin_signature']))
    	{
			$id = get_post($conn, 'del_admin_content_id');
			$content_name = get_post($conn, 'del_admin_content_name');
			$signature = get_post($conn, 'del_admin_signature');
			
			$query = "DELETE FROM virus_information WHERE id='$id' AND virus_name='$content_name' AND signature_20_bytes='$signature'";
			$result = $conn->query($query);
			if (!$result) 
			{
				echo "DELETE failed: $query<br>" .
				$conn->error . "<br><br>";
			}
			//no need to close $result object.
    	}
	}

	function deleteCollaboratorContent($conn)
	{
		if (isset($_POST['delete_collaborator_content']) && 
			isset($_POST['del_collaborator_content_id']) && 
			isset($_POST['del_collaborator_username']) &&
			isset($_POST['del_collaborator_content_name']) && 
			isset($_POST['del_collaborator_signature']))
    	{
			$id = get_post($conn, 'del_collaborator_content_id');
			// $username = get_post($conn, 'del_collaborator_username');
			// $content_name = get_post($conn, 'del_collaborator_content_name');
			// $signature = get_post($conn, 'del_collaborator_signature');
			
			$query = "DELETE FROM virus_putative_information WHERE id='$id'";
			$result = $conn->query($query);
			if (!$result) 
			{
				echo "DELETE failed: $query<br>" .
				$conn->error . "<br><br>";
			}
			//no need to close $result object.
    	}
	}

	function movePutativeContent($conn)
	{
		if (isset($_POST['move_collaborator_content']) && 
			isset($_POST['del_collaborator_content_id']) && 
			isset($_POST['del_collaborator_username']) &&
			isset($_POST['del_collaborator_content_name']) && 
			isset($_POST['del_collaborator_signature']))
    	{

			$id = get_post($conn, 'del_collaborator_content_id');
			$username = get_post($conn, 'del_collaborator_username');
			$content_name = get_post($conn, 'del_collaborator_content_name');
			$signature = get_post($conn, 'del_collaborator_signature');

			//Add to Admin Virus Table
			$query = "INSERT INTO virus_information (virus_name, signature_20_bytes) VALUES" .
			"('$content_name', '$signature')";

			$result = $conn->query($query);
			if (!$result) 
			{
			echo "INSERT failed: $query<br/>" . $conn->error . "<br/><br/>";
			}

			//Remove from Collaborative Virus Table
			$query = "DELETE FROM virus_putative_information WHERE id='$id'";
			$result = $conn->query($query);
			if (!$result) 
			{
				echo "DELETE failed: $query<br>" .
				$conn->error . "<br><br>";
			}
		}
	}

	function displayAdminContent($conn)
	{
		echo "<b>Displaying Admin Content:</b><br/>";
		$query = "SELECT * FROM virus_information";
		$result = $conn->query($query);
		if (!$result) 
		{
			mysql_fatal_error("Database access failed", $conn);
			return;
		}
		$rows = $result->num_rows;
		if($rows === 0) 
		{
			echo "No Content Available: User Content Table is Empty.<br/>";
		}
		for ($j = 0 ; $j < $rows ; ++$j)
		{
			$result->data_seek($j);
			$row = $result->fetch_array(MYSQLI_NUM);
			echo <<<_END
				<pre>
					id: $row[0]
					Content Name: $row[1]
					Signature: $row[2]
				</pre>
				<form action="virus_login.php" method="post">
					<input type="hidden" name="delete_admin_content" value="yes">
					<input type="hidden" name="del_admin_content_id" value="$row[0]">
					<input type="hidden" name="del_admin_content_name" value="$row[1]">
					<input type="hidden" name="del_admin_signature" value="$row[2]">
					<input type="submit" value="Delete">
				</form>
				_END;
		}
		$result->close();
	}

	function displayCollaboratorContentForAdmin($conn)
	{
		echo "<b>Displaying All Collaborator Content:</b><br/>";

		$query = "SELECT * FROM virus_putative_information";
		$result = $conn->query($query);

		if (!$result)
		{
			mysql_fatal_error("Database access failed", $conn);
			return;
		}

		$rows = $result->num_rows;
		if($rows === 0) 
		{
			echo "No Content Available: User Content Table is Empty.<br/>";
		}

		for ($j = 0 ; $j < $rows ; ++$j)
		{
			$result->data_seek($j);
			$row = $result->fetch_array(MYSQLI_NUM);
			echo <<<_END
				<pre>
					id: $row[0]
					username: $row[1]
					Content Name: $row[2]
					Signature: $row[3]
				</pre>
				<form action="virus_login.php" method="post">
					<input type="hidden" name="del_collaborator_content_id" value="$row[0]">
					<input type="hidden" name="del_collaborator_username" value="$row[1]">
					<input type="hidden" name="del_collaborator_content_name" value="$row[2]">
					<input type="hidden" name="del_collaborator_signature" value="$row[3]">
					<input type="submit" name="delete_collaborator_content" value="Delete"><input type="submit" name="move_collaborator_content" value="Move">
				</form>
				_END;
		}
		$result->close();
	}

	function displayCollaboratorContentForCollaborator($conn, $username)
	{
		echo "<b>Displaying User: $username's Collaborator Content:</b><br/>";

		$query = "SELECT * FROM virus_putative_information WHERE username='$username'";
		$result = $conn->query($query);

		if (!$result)
		{
			mysql_fatal_error("Database access failed", $conn);
			return;
		}

		$rows = $result->num_rows;
		if($rows === 0) 
		{
			echo "No Content Available: User Content Table is Empty.<br/>";
		}

		for ($j = 0 ; $j < $rows ; ++$j)
		{
			$result->data_seek($j);
			$row = $result->fetch_array(MYSQLI_NUM);
			echo <<<_END
				<pre>
					id: $row[0]
					username: $row[1]
					Content Name: $row[2]
					Signature: $row[3]
				</pre>
				<form action="virus_login.php" method="post">
					<input type="hidden" name="del_collaborator_content_id" value="$row[0]">
					<input type="hidden" name="del_collaborator_username" value="$row[1]">
					<input type="hidden" name="del_collaborator_content_name" value="$row[2]">
					<input type="hidden" name="del_collaborator_signature" value="$row[3]">
					<input type="submit" name="delete_collaborator_content" value="Delete">
				</form>
				_END;
		}
		$result->close();
	}

    function displayLogout()
	{
		echo <<<_END
		<form action="virus_login.php" method="post">
		<input type='submit' name='logoutBtn' value="Logout">
		</form>
		_END;
		echo "<hr/>";
	}

    function loginUser($conn)
	{
		$loginSuccessful = false;
		$username = "";
        $role = "";
		if (isset($_POST['login_username']) && isset($_POST['login_password']) &&
		!empty($_POST['login_username']) && !empty($_POST['login_password']))
		{
			$username = get_post($conn, 'login_username');
            $username = strtolower($username);
			$password = get_post($conn, 'login_password');

			$query = "SELECT * FROM users WHERE username='$username'";

			$result = $conn->query($query);

			if($result)
			{
				$rows = $result->num_rows;
			
				//if username doesn't exist
				if($rows === 0) 
				{
					$result->close();
					return [$loginSuccessful, "", ""];
				}
				
				$result->data_seek(0);
				$row = $result->fetch_array(MYSQLI_ASSOC);
				$result->close();
				//if password matches
				if ( password_verify($password, $row['token']) ) 
				{
					setcookie("username", $username, time() + 60 * 60 * 24 * 7, '/');
                    setcookie("role", $row['role'], time() + 60 * 60 * 24 * 7, '/');
                    $role = $row['role'];
					$loginSuccessful = true;
				} 
			}
		}

		return [$loginSuccessful, $username, $role];
	}

    function displaySignInForm()
	{
		echo <<<_END
		<hr/>
		<pre>
		█░░ █▀█ █▀▀ █ █▄░█
		█▄▄ █▄█ █▄█ █ █░▀█
		</pre>
		_END;
		echo <<<_END
		<form action="virus_login.php" method="post">
		<pre>
		<label for="login_username">Username</label>
		<input type="text" name="login_username" value="" required>
		<label for="login_password">Password</label>
		<input type="password" name="login_password" value="" required>
		<input type="submit" name="loginBtn" value="Login">
		</pre>
		</form>
		_END;
	}

    function displayRegisterForm()
	{	
		echo "<hr/>";
		echo <<<_END
		<pre>
		█▀█ █▀▀ █▀▀ █ █▀ ▀█▀ █▀█ ▄▀█ ▀█▀ █ █▀█ █▄░█
		█▀▄ ██▄ █▄█ █ ▄█ ░█░ █▀▄ █▀█ ░█░ █ █▄█ █░▀█
		</pre>
		_END;
		echo <<<_END
		<form action="virus_login.php" method="post">
		<pre>
		<label for="reg_username">Username</label>
		<input type="text" name="reg_username" value="" autoComplete='off' required>
		<label for="reg_email">Email</label>
		<input type="email" name="reg_email" value="" autoComplete='off' required>
		<label for="reg_password">Password</label>
		<input type="password" name="reg_password" value="" required>
		<input type="submit" name="registerBtn" value="Register">
		</pre>
		</form>
		_END;
	}

    function registerUser($conn)
	{
		if (isset($_POST['reg_username']) && isset($_POST['reg_email']) &&
		!empty($_POST['reg_username']) && !empty($_POST['reg_email']) &&
		isset($_POST['reg_password'])  && !empty($_POST['reg_password']) )
		{
			$username = get_post($conn, 'reg_username');
            $username = strtolower($username);
			$email = get_post($conn, 'reg_email');
			$password = get_post($conn, 'reg_password');
            $role = "collaborator";
            //Admin role's can only be added manually with the mysql cli.

			$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

			$query = "INSERT INTO users VALUES" .
			        "('$username', '$email', '$hashedPassword', '$role')";

			$result = $conn->query($query);
			
            if (!$result) 
			{
				echo "INSERT failed: $query<br>" . $conn->error . "<br><br>";
			}
			else 
            {
				echo "Registered user: '$username'<br/>";
				// $result->close();
				//you can't close this type of query object
				//because it post to database and does not contain
				//anything.
			}
		}
	}

	function initialFileCheck()
	{
		if($_FILES && isset($_POST['content_name']))
		{		
			return isValidContentName( htmlentities( $_POST['content_name']) );
		} else {
			return false;
		}
	}

	function isValidContentName($name)
	{
		if(strlen($name) < 1) return false;

		for( ; strlen($name) > 0; )
		{
			if(!ctype_alnum(substr($name, 0, 1)))
			{
				return false;
			}
			$name = substr($name, 1, strlen($name));
		}

		return true;
	}

	function displayFileUploadForm($username)
	{
		echo "<h3>Add Content To Account: '$username'</h3>";
		echo <<<_END
		<form method='post' action='virus_login.php' enctype='multipart/form-data'>
		<pre>
		<label for="content_name">Content Name</label>
		<input type='text' name='content_name' value='' required>
		<label for="filename">Select File:</label>
		<input type='file' name='filename' size='10' required><br/>
		<input type='submit' value='Upload'>
		</pre>
		</form>
		_END;
	}

    // SANTIZE FUNCTIONS
	function mysql_entities_fix_string($conn, $string) {
		return htmlentities(mysql_fix_string($conn, $string));
	}

	function mysql_fix_string($conn, $string) {
		return $conn->real_escape_string($string);
	}

	function get_post($conn, $var)
	{
		return $conn->real_escape_string($_POST[$var]);
	}

	$conn->close();
?>