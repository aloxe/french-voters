<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>French voters (TEST)</title>
</head>
<body>

<?php
$dossier = $_SERVER['DOCUMENT_ROOT'] . "data/upload/";
$fichier = basename($_FILES['upfile']['name']);
$taille_maxi = 1000000;
$taille = filesize($_FILES['upfile']['tmp_name']);
$extensions = array('.txt', '.csv', '.jpg', '.jpeg', '.png');
$extension = strrchr($_FILES['upfile']['name'], '.'); 

echo "<h2>File upload status:</h2><pre>";
print_r($_FILES);
echo "</pre>";

//Début des vérifications de sécurité...
if(!in_array($extension, $extensions)) //Si l'extension n'est pas dans le tableau
  {
    $erreur = $extension . " à comparer avec "  . $extensions . ". Vous devez uploader un fichier de csv ou txt ou doc...";
  }
if($taille>$taille_maxi)
  {
    $erreur = 'Le fichier est trop gros... 1Mo minimum';
  }
if(!isset($erreur)) //S'il n'y a pas d'erreur, on upload
  {
    //On formate le nom du fichier ici...
    $fichier = strtr($fichier, 
		     'ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ', 
		     'AAAAAACEEEEIIIIOOOOOUUUUYaaaaaaceeeeiiiioooooouuuuyy');
    $fichier = preg_replace('/([^.a-z0-9]+)/i', '-', $fichier);
    echo "<!--pre>Uploading " . $fichier . " to " . $dossier . "</pre><br-->";
    if(move_uploaded_file($_FILES['upfile']['tmp_name'], $dossier . $fichier)) //Si la fonction renvoie TRUE, c'est que ça a fonctionné...
      {
	echo '<em>Upload effectué avec succès !</em><br>You can now proceed to the <a href="analyse.php">analysis of the data</a>';
      }
    else //Sinon (la fonction renvoie FALSE).
      {
	echo 'Echec de l\'upload !<br>';
      }
  }
 else
   {
     echo "ERROR:" . $erreur . '<br>';
   }
?>
</body>
</html>
