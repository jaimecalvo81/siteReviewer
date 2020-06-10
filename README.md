# siteReviewer
Crawls site to checks links, takes screenshots to compare and show difference 

This is the main page for regression and smoke test of existing functionality. 
We use Phantomjs, PhantomCSS and Casperjs. 
You will need to add them to your system. 
I used npm for phantomjs:
sudo npm install -g phantomjs
Casperjs need Phantom to at version 1.x. 
I tried npm for casperjs but didn't work.
So i used git. 
$ git clone git://github.com/n1k0/casperjs.git
$ cd casperjs 
$ ln -sf `pwd`/bin/casperjs /usr/local/bin/casperjs
 
read more at: 
http://docs.casperjs.org/en/latest/installation.html

 
To run test go to root folder and run: 
casperjs test test/main.js --url="https://www.yourdomain.com" --shot=1 --crawl=1 --regexp='#|ftp|javascript|.pdf'
or:
casperjs test test/main.js --urllist="urls.js" --crawl=0
 

| Arg      | Options                                                          | If not suppled        |
| -------- | ---------------------------------------------------------------- | --------------------- |
| url      | starting url. !OBS ! this will be overwritten if you use urllist | https://www.viasat.se |
| shot     | 1 or 0, if it should take screenshot or not.                     | 1                     |
| crawl    | 1 or 0, if it should crawl for links on each page.               | 1                     |
| urllist  | a file in test folder that populate pendingUrls, see urls.js     | none                  |
| regexp   | Regexp for which urls NOT to test, will effect urllist also      | #|ftp|javascript|.pdf |
| viewport | 320 or 600 or 1024 or 1280 or 1440                               | all of them           |

 
If you run a urlist and the regexp finds one url in the list it will not test that url.
Also if crawl is true it will crawl the urls on the list for more urls.
It will prompt in the terminal, which url are added, not tested, http respons of tested url.
Screenshot will be made in 320, 600, 1024,1280 and 1440 if no viewport is selected then only that viewport. 
 
Screenshot will be saved under screenshots/urlpar1/urlpart2/size.png
parts are parts from url, example: viasat.se/familj/barn -> screenshots/viasat.se/familj/barn/320.png

First run will only screenshot. Second run will compare and if it's different it will add a file do /failure with a dubble image that will show what's different.  

