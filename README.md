BYU Class iCal (Generator)
=============
This is a PHP script to get your Brigham Young University (BYU) class schedule as an iCal file.


Hosting
-------------
You will have to install it yourself on a \*.byu.edu domain with HTTPS. Or hack up the code and inject the BYU-Web-Session cookie after obtaining one in your browser.


Caveats and TODOs
-------------
It doesn't know about exceptions to the class schedule, or add stuff like exams, but hopefully it's a nice start, especially if you're going to be juggling your schedule around in the first few weeks of class. I plan to at least get the final exams in there soon. Holidays and Monday/Friday Instruction are doable too, but are a pain.


More on Security
-------------
Here's some [documentation](https://developer.byu.edu/wiki/display/OITCoreDeveloperResources/Browser-based+Web+Service+Calls+using+a+Session+Authentication+Tutorial) on the cookie stealing; it's an intentional open door
to do stuff like this. I may not be one of "OIT Core's campus partners whom [they] trust", but I'm not malicious. It's unfortunate that when they close this cookie scope loophole, arbitrary guys like me won't be able to do helpful stuff like this without applying for an API key we'll likely be denied, or taking the user's credentials. I'll have to look into other options.
