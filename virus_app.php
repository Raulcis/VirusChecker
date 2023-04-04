<?php
    require_once 'login.php';
    $conn = new mysqli($hn, $un, $pw, $db); 

    echo "To sign in as admin/collaborator click <a href='virus_login.php'>here</a>";
    
    displayFileUploadForm();

    if(initialFileCheck())
    {
        echo "File uploaded!<br/>RESULTS:<br/>";
        $contents = getAllFileContents($_FILES['filename']['tmp_name']);

        lookForViruses($conn, $contents);
    }

    function displayFileUploadForm()
	{
		echo "<h3>Check File For Virus:</h3>";
		echo <<<_END
		<form method='post' action='virus_app.php' enctype='multipart/form-data'>
		<pre>
		<label for="filename">Select File:</label>
		<input type='file' name='filename' size='10' required><br/>
		<input type='submit' value='Upload'>
		</pre>
		</form>
		_END;
	}

    function initialFileCheck()
	{
		if($_FILES)
		{		
			return true;
		} else {
			return false;
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

    function lookForViruses($conn, $file_contents)
    {
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
			echo "No Content Available: User Virus Table is Empty.<br/>";
		}

        $isVirusFree = true;

		for ($j = 0 ; $j < $rows ; ++$j)
		{
			$result->data_seek($j);
			$row = $result->fetch_array(MYSQLI_NUM);
            if(containsSignature($file_contents, $row[2]))
			{
                echo "Contains Virus: $row[1]<br/>";
                $isVirusFree = false;
            }
		}

        if($isVirusFree)
        {
            echo "The file uploaded does not contain a virus!<br/>";
        }

		$result->close();
    }

    function containsSignature($contents, $signature)
    {
        return str_contains($contents, $signature);
    }

    $conn->close();
?>