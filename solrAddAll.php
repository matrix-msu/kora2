<?php 
require_once("includes/solrUtilities.php");

set_time_limit(500);

processDirectory("files");

$req_url = solr_url."update?commit=true"; // Curl request URL, to commit everything
$ch = curl_init($req_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Return the string instead of auto-printing it
$result = curl_exec($ch); // Execute
curl_close($ch); // Close Curl


function processFile($path, $file)
{
    $relativePath = "$path/$file"; // Path to the file

    $idarray = explode('-', $file);
    
    $rid = "$idarray[0]-$idarray[1]-$idarray[2]";
    $cid = $idarray[3]; // FULL ID, of form PID-SID-RID-CID
    
    // Allow solrUtilities.php to do the real work
    // This can be changed to deleteFromSolrIndexByRID($rid, $cid) in order to clear the index,
    // or the index can be easily cleared from command prompt with 
    // curl "http://your.server.here:8983/solr/update?commit=true" -H "Content-Type: text/xml" --data-binary "<delete><query>*:*</query></delete>"
    return addToSolrIndexByRID($rid, $cid, false, false);
    //return deleteFromSolrIndexByRID($rid, $cid, false)
}


function processDirectory($path)
{
    $handle = opendir($path);
    
    // Ignore the self, the parent folder, and those other--makes recursion work and doesn't do awaitingApproval
    $ignore = array(".", "..", "thumbs", "awaitingApproval", "extractedFiles");
    $typelist = array("application/msword", "application/pdf", "text/plain");

    // While there are still more files in this directory
    while( $file = readdir($handle) )
    {
        if ( !in_array($file, $ignore) ) // Check to see if it's an ignore
        {
            if ( is_dir("$path/$file") )
            {
                echo "Checking directory: $path/$file <br /><br />\n\n";
            	processDirectory("$path/$file"); // Recursively call on next level down    
            }
            else
            {
                $finfo = finfo_open(FILEINFO_MIME_TYPE); // Find the MIME type
                $type = finfo_file($finfo, "$path/$file");
                finfo_close($finfo);
                
                
                if ( $path != "." && in_array($type, $typelist) ) // Skip files in the same directory as this script. Should never be a problem.
                {
                    // echo "Processing file: $path/$file <br />\n"; // Status info for each file
                    
                    if (processFile($path, $file)) // Success
                    {
                        echo "File processed : $path/$file <br /><br />\n\n";
                    }
                    else // Failure
                    {
                        echo "ERROR: Failed to process file: $path/$file <br /><br />\n\n";
                    }
                    
                }
                // else echo "Unable to index file (invalid type): $path/$file <br />\n";
            }
        }
    }
    
    closedir($handle); // Close the handle
    
    return true;
}
?>
