<?php
/**
 * Translation file
 *
 * Note: don't change the return array to short notation because Transifex can handle those during `tx push -s`
 */

return array(

	/**
	 * Menu items and titles
	 */

	'item:object:page' => 'Σελίδες',
	'collection:object:page' => 'Σελίδες',
	'collection:object:page:all' => "Όλες οι σελίδες",
	'collection:object:page:owner' => "σελίδες του χρήστη %s",
	'collection:object:page:friends' => "Σελίδες φίλων",
	'collection:object:page:group' => "Σελίδες ομάδας",
	'add:object:page' => "Προσθήκη Σελίδας",
	'edit:object:page' => "Επεξεργασία σελίδας",

	'groups:tool:pages' => 'Ενεργοποίηση σελίδων ομάδας',
	
	'annotation:delete:page:success' => 'The page revision was successfully deleted',
	'annotation:delete:page:fail' => 'The page revision could not be deleted',

	'pages:delete' => "Διαγραφή σελίδας",
	'pages:history' => "Ιστορικό",
	'pages:view' => "Προβολή σελίδας",
	'pages:revision' => "Αναθεώρηση",

	'pages:navigation' => "Πλοήγηση",

	'pages:notify:summary' => 'Νέα σελίδα με τίτλο %s',
	'pages:notify:subject' => "Νέα σελίδα: %s",
	'pages:notify:body' =>
'%s added a new page: %s

%s

View and comment on the page:
%s',

	'pages:more' => 'Περισσότερες σελίδες',
	'pages:none' => 'Δεν υπάρχουν ακόμα σελίδες',

	/**
	* River
	**/

	'river:object:page:create' => '%s created a page %s',
	'river:object:page:update' => '%s updated a page %s',
	'river:object:page:comment' => '%s commented on a page titled %s',
	
	/**
	 * Form fields
	 */

	'pages:title' => 'Τίτλος σελίδας',
	'pages:description' => 'Κείμενο σελίδας',
	'pages:tags' => 'Ετικέτες',
	'pages:parent_guid' => 'Γονική σελίδα',
	'pages:access_id' => 'Δικαίωμα ανάγνωσης',
	'pages:write_access_id' => 'Δικαίωμα εγγραφής',

	/**
	 * Status and error messages
	 */
	'pages:cantedit' => 'Δεν μπορείτε να επεξεργαστείτε αυτή τη σελίδα',
	'pages:saved' => 'Η σελίδα αποθηκεύτηκε',
	'pages:notsaved' => 'Η σελίδα δεν μπορεί να αποθηκευτεί',
	'pages:error:no_title' => 'Πρέπει να καθορίσετε έναν τίτλο για αυτή τη σελίδα.',
	'entity:delete:object:page:success' => 'Η σελίδα διαγράφτηκε επιτυχώς.',
	'pages:revision:delete:success' => 'Η αναθεώρηση της σελίδας διαγράφτηκε επιτυχώς',
	'pages:revision:delete:failure' => 'Δεν ήταν δυνατή η αναθεώρηση της σελίδας',

	/**
	 * History
	 */
	'pages:revision:subtitle' => 'Δημιουργήθηκε αναθεώρηση %s από %s',

	/**
	 * Widget
	 **/

	'pages:num' => 'Αριθμός σελίδων για εμφάνιση',
	'widgets:pages:name' => 'Σελίδες',
	'widgets:pages:description' => "Λίστα των σελίδων σας.",

	/**
	 * Submenu items
	 */
	'pages:label:view' => "Προβολή σελίδας",
	'pages:label:edit' => "Επεξεργασία σελίδας",
	'pages:label:history' => "Ιστορικό σελίδας",

	'pages:newchild' => "Δημιουργία υποσελίδας",
	
	/**
	 * Upgrades
	 */
	'pages:upgrade:2017110700:title' => "Migrate page_top to page entities",
	'pages:upgrade:2017110700:description' => "Changes the subtype of all top pages to 'page' and sets metadata to ensure correct listing.",
	
	'pages:upgrade:2017110701:title' => "Migrate page_top river entries",
	'pages:upgrade:2017110701:description' => "Changes the subtype of all river items for top pages to 'page'.",
);
