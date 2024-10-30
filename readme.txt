===IPManager Connector===
Contributors: mwdmeyer
Tags: tickets, btev, support, soap, ipmanager, plugin, kaseya
Requires at least: 3.1.1
Tested up to: 3.1.1
Stable tag: 0.1

IPManager Connector (IPMC) allows you to submit support tickets directly into IPManager.

== Description ==

IPManager Connector (IPMC) allows you to submit support tickets directly into IPManager.

IPManager Connector uses a simple HTML tag so that you can insert the Ticket Form into any existing Post or Page.

For more information about IPManager please visit http://www.dalegroup.net/page/ipmanager/

== Installation ==

1. Download
1. Unzip
1. Upload the unzipped folder to wp-content/plugins
1. Activate within Wordpress Admin Panel
1. Enter SOAP connection details of the IPManager Server (found in the IPManager general settings page)
1. Enable "Allow Anonymous Support Tickets" from within IPManager
1. Create a new Post or Page with the following HTML tag <!--ipm_anon_support_form-->

== Frequently Asked Questions ==

= What does LOCKDOWN mode do? =

The LOCKDOWN mode is designed to make it more difficult to disable IPMC.  
It might be required for extra security or if you don't want users with Administrator permissions to be able to disable the plugin.
In LOCKDOWN mode the follow options are disabled:

1. Unregister Server
1. Update Settings
1. Uninstall
1. Deactivate

This can make it more affective in synchronising changes that occur in your site.

= How do I enable LOCKDOWN mode? =

open ipm.php and find the line (near the top) that says:

		define('IPM_LOCKDOWN', FALSE);
and change it to:

		define('IPM_LOCKDOWN', TRUE);
		
Remember to upload the file if you edited it locally.

= What limitations does LOCKDOWN mode have? =

1. Please be aware that LOCKDOWN mode is NOT a guarantee that IPMC will say active if you site is hacked.
1. An extra layer of security is added but there are many other ways to disabled IPMC.  
1. It is recommended that ipm.php is NOT writable so that the file cannot be editted from within WordPress.
1. Please be aware that LOCKDOWN mode is NOT a guarantee that IPMC will say active if you site is hacked.

= IPMC has a bug and/or I want to suggest some new features. How? =

Please contact Dalegroup Pty Ltd here: http://www.dalegroup.net/contact/

== Changelog ==

= 0.1 - 25/04/2011 =

* First Release  