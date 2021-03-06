<?php
/**
 * This file is intended to demonstrate what can be done out of the box without
 * creating special project-specific classes. Everything in this demo can be
 * done by simply including /includes.php.
 */

// the test config file simply includes the library and defines SEARCH_TOKEN
require_once( __DIR__ . '/config.php' );

// create an instance of KoraManager
$manager = new \KoraORM\KoraManager(SEARCH_TOKEN);

// search for some photos
$photos = $manager->search(PROJECT_ID, SCHEME_ID, new \KORA_Clause('KID', '!=', ''));

// $authors is now an array of \KoraORM\KoraEntities. Furthermore, they are
// from the author scheme defined in our config file which has a Text control
// named Name and a Record Associator control named Books. These
// \KoraORM\KoraEntities now have properties called Name which is a TextControl
// and Books which is an AssociatorControl.

// Now lets create some output.

?>

<html>
 <head>
  <title>KoraORM - Basic Example</title>
 </head>
 <body>
  <h1>KoraORM - Basic Example</h1>
  <h2>Printing Search Results</h2>
<?php foreach ($photos as $photo): ?>
  <div class="photo" style="border-style: solid">
   <h3><?php echo htmlspecialchars($photo->Title); ?></h3>
   <p><strong>Taken</strong>: <?php echo htmlspecialchars($photo->Taken); ?></p>
   <p><img src="<?php echo htmlspecialchars($photo->Photo->thumbUrl); ?>" /></p>
<?php foreach ($photo->Categories as $category): ?>
   <p><strong>Category</strong>: <?php echo htmlspecialchars($category); ?></p>
<?php endforeach; ?>
  </div>
<?php endforeach; ?>
 </body>
</html>