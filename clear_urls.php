<?php
$mysqli = new mysqli("localhost", "root", "bagusdev123", "url_shortener");

// Check connection
if ($mysqli->connect_errno) {
  echo "Failed to connect to MySQL: " . $mysqli->connect_error;
  exit();
}

$mysqli->query("TRUNCATE TABLE urls");
echo "Table urls truncated successfully.\n";
