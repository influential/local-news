# local-news-finder
Finds local news articles within a given mile radius of a location.
### Includes
* CSV file containing all news websites in the United States with their latitude/longitude and city/state.
* CSV file containing all sitemaps found on the news websites.
* AWS Lambda Module
* PHP file containing primary application functionality
* PHP file that provides an example front end to the application.
* PHP file containg AWS SDK

### Concepts
To find local news near a certain agent's location, we need to...
1. Find local news station websites near the agent.
2. Extract news articles from those sites.

Each major news network (ABC, NBC, FOX, CBS) has a list of local affiliates on their website. We've already extracted those manually and validated them (listings are not very up to date, it cannot be done in a programmatic, reliable way), and then we calculated the coordinates of each station using the Google Geocoding API.

Then using Haversine's formula, we can get the coordinates of any US location and find news stations within a given radius of that location. That gives us a list of news stations to extract news articles from.

The two most reliable ways to extract news articles we could come up with were:

1. Use XML sitemaps.
2. Crawl the site.

But about 1/3 of the news websites actually have sitemaps, and crawling news websites continually can take a long time, we want the list of news articles to be delivered quickly.

So the current strategy is to:

1. Find all sitemaps on the news websites, store the URL of the sitemaps and pull them from the database when needed. Get links to news articles from these.
2. Extract all news article links from just the homepage of the site.

To find all sitemaps for a site and extract links from them:

1. Load the robots.txt file, usually sitemap URLS are listed there, almost every site has a robots.txt file.
2. If the site doesn't have a robots.txt, just check newswebsite.com/sitemap.xml, if there is nothing there, then the site has no sitemaps (that we can reliably locate).
3. If #2 is not true, load each of the sitemaps linked to in the robots.txt or the one located at newswebsite.com/sitemap.xml (if no robots.txt). Some sites have sitemaps that include sitemaps. If we find sitemaps within these first sitemaps we discover, we then also check them. We check a sitemap by seeing if it has a decent number of <url> sections that indicate a link to an article.
4. We don't add a sitemap if in the URL there are certain strings, like "2014" for example, because that's an old sitemap.
5. Only check one level deep to see if the sitemap contains other sitemaps (to avoid a loop).
6. This results in minimal requests being made, so the news website blocking our crawler is avoided. Usually a site has 2-3 sitemaps, all linked from the robots.txt.
7. Extracting links is easy, get the first 50 or so links from the sitemap, if a date is given for a URL check to see if it is not within days, if not, don't keep it. You can probably filter by length again.
8. We try to locate all valid sitemaps for every site at once, then store all valid sitemaps in the database. Occasionally recheck to make sure the website's sitemaps haven't changed.

To extract links from a news website homepage:

1. Extract all href="url" patterns.
2. If it is a local path (href="/home") add the news site domain to the front of it.
3. Otherwise make sure it is a least a certain length to weed out URL shorteners or irrelevant links, and make sure it is from the same domain as the news site.

Once we've done this we have a list of links that may or may not be news articles. We need to filter them the best we can using a ranking algorithm.

We rank them based on:
1. Does the link have a unique date pattern in the URL? (2015/04/25)
2. Does the link have a unique word sequence in the URL? (local-man-saves-boy-from-water)
3. Does the link have a unique numer pattern in the URL? (articleid=123456)
4. How many Facebook shares does the link have?
5. How long is the link?

All of these things tend to correlate to a URL linking to a news article rather than linking to an "about us" or "contact" page.

After ranking we return the result back to the user.

All of these ideas are implemented in the current version, but could definitely be improved in some ways, and edge cases could be covered better.

### Database
The data is stored in CSV files, these can easily be imported into a MySQL database structured as follows...
* Database: data
* Table #1: tables
* Table #2: sitemaps
* Columns for tables: url, parent, name, city, state, latitude, longitude
* Columns for sitemaps: site, sitemap

Primary keys for both tables are the news website URL (column site and column url).
### Room For Improvement
1. Alternate ways of efficiently discovering new local news articles posted by sites (social media especially).
2. Validating the date of news articles better.
3. Crawling more than just the homepage, maybe looking for a /news or /localnews link in the menu and crawling that page too. Often too few news article links are returned from crawling homepages.

### Important Aspects
1. Minimizing HTTP requests from server to save time and avoid being blocked from website.
2. Asynchronously launching HTTP requests on Lambda, and doing any "extensive crawling" on Lambda because the Lambda instance IP Address changes frequently (helps mitigate blocking).
3. Keeping it cheap! This runs pretty fast and AWS Lambda is cheap.

### Setup
Follow the instructions for installing the AWS Lambda SDK given here: http://docs.aws.amazon.com/aws-sdk-php/v2/guide/installation.html, and that's basically it! Just setup a PHP server and everything should run fine. Remember you'll need to get the Lambda component set up and the database setup as well.

I would recommend seeding the sitemaps again, the function that seeds them may need slight adjusting, but it shouldn't be too much.
