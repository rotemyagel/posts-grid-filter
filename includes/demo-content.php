<?php
/**
 * The demo posts the seeder creates. Categories are listed primary first.
 * Every category has four posts, four posts have two categories, and every
 * tag is used, so filter combinations give different results.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	array(
		'title'      => 'The Future of Headless WordPress',
		'categories' => array( 'Technology', 'Business' ),
		'tags'       => array( 'Deep Dive', 'Trends', 'Opinion' ),
		'excerpt'    => 'Decoupled front ends promised speed and freedom. A few years in, the teams that got real value are the ones that kept the editor at the centre.',
		'body'       => array(
			'Headless WordPress was sold as a clean split: WordPress manages content, a separate application renders it. For large publishers with a dedicated front-end team, that split pays off. For everyone else, it often doubles the stack to maintain while quietly breaking previews, forms and plugins that assumed a theme.',
			'The more useful question is which parts of the site need to be decoupled at all. Most pages are content that the block editor already renders well. The interactive parts, like search, filtering and checkout, can often be built as blocks with the Interactivity API instead of as a separate app.',
			'The teams we spoke to who were happiest after two years had one thing in common: they measured editorial speed, not just page speed. When authors could see exactly what they were publishing, everything else was easier to fix.',
		),
	),
	array(
		'title'      => 'A Designer\'s Guide to Design Tokens',
		'categories' => array( 'Design' ),
		'tags'       => array( 'Guide', 'Deep Dive' ),
		'excerpt'    => 'Tokens turn a style guide into something code can check. Start with fewer than you think you need, and name them for their purpose, not their value.',
		'body'       => array(
			'A design token is a named decision: the size of body text, the colour of a focus ring, the radius of a card. Naming it once and using the name everywhere means a change happens in one place instead of forty.',
			'The most common mistake is creating too many. Six text sizes invite a seventh. Three sizes and two weights force a real hierarchy, and a linter can reject anything outside the set before it reaches review.',
			'Name tokens for their role, like text-small or accent, rather than their value, like blue-500. Roles survive a rebrand; values do not.',
		),
	),
	array(
		'title'      => 'Scaling a Small Team Without Losing Culture',
		'categories' => array( 'Business', 'Culture' ),
		'tags'       => array( 'Guide', 'Opinion' ),
		'excerpt'    => 'Going from five people to fifteen changes how decisions travel. Write things down before you need to, and protect the habits that made the team work.',
		'body'       => array(
			'In a team of five, everyone hears every decision. At fifteen, most people learn about decisions second-hand, and small misunderstandings compound. The fix is not more meetings but better written records: short decision notes that say what was chosen and why.',
			'New people copy what they see, not what the handbook says. If reviews are kind and specific, they will be kind and specific. If deadlines slip quietly, that becomes the norm too.',
			'Pick the two or three habits that made the early team work and make them explicit. Everything else can change as the team grows.',
		),
	),
	array(
		'title'      => 'What the Interactivity API Changes for Block Authors',
		'categories' => array( 'Technology' ),
		'tags'       => array( 'Deep Dive', 'Guide', 'News' ),
		'excerpt'    => 'Server-rendered blocks can now share state and react to clicks without a front-end framework. Here is what that means for how you structure a block.',
		'body'       => array(
			'Before the Interactivity API, a block that needed behaviour on the front end usually shipped its own script and its own conventions. Two blocks on the same page had no standard way to talk to each other.',
			'Now blocks declare their behaviour with data-wp directives and share a store by namespace. The server renders the first view, the browser hydrates it, and the router can swap in newly rendered regions without a full page load.',
			'The practical change for authors is that the PHP render callback stays the source of truth. JavaScript describes what changes, not how to build the markup again.',
		),
	),
	array(
		'title'      => 'Notes on Building a Sustainable Freelance Business',
		'categories' => array( 'Business' ),
		'tags'       => array( 'Opinion', 'Guide' ),
		'excerpt'    => 'Most freelancers price by the hour and burn out by the year. Retainers, clear scopes and fewer, better clients make the work last.',
		'body'       => array(
			'Hourly billing rewards being slow and punishes getting better at your job. Pricing by outcome or by retainer lets you keep the benefit of your own experience.',
			'A clear scope is a kindness to both sides. Writing down what is not included is as important as listing what is, because that is where most disagreements start.',
			'The last lesson is the hardest: say no to projects that do not fit. Every bad-fit client takes the time that a good one would have filled.',
		),
	),
	array(
		'title'      => 'The Quiet Return of Skeuomorphism',
		'categories' => array( 'Design', 'Culture' ),
		'tags'       => array( 'Trends', 'Opinion' ),
		'excerpt'    => 'After a decade of flat design, texture, depth and physical metaphors are creeping back into interfaces. This time they are doing real work.',
		'body'       => array(
			'Flat design solved a real problem: interfaces full of fake leather and glossy buttons were noisy and hard to scale. But it also removed cues that told people what was clickable.',
			'The new wave is restrained. Soft shadows show what can be pressed, subtle depth separates layers, and materials hint at how a control behaves.',
			'The test for any of it is simple: does the detail help someone understand the interface faster? If it only decorates, it will date quickly.',
		),
	),
	array(
		'title'      => 'Why Editorial Calendars Fail (and What Works Instead)',
		'categories' => array( 'Culture' ),
		'tags'       => array( 'Opinion', 'Guide', 'Trends' ),
		'excerpt'    => 'A calendar full of publish dates says nothing about whether anyone has time to write. Plan capacity first, and the calendar follows.',
		'body'       => array(
			'Most editorial calendars are wish lists with dates attached. They fail when the dates are set before anyone asks who will write, edit and review each piece.',
			'Teams that publish reliably plan the other way round. They start from how many hours each person really has, then fill the calendar with what fits.',
			'Keep a small backlog of evergreen pieces that are nearly ready. When a planned article slips, something good still goes out.',
		),
	),
	array(
		'title'      => 'An Interview with a Block Theme Maintainer',
		'categories' => array( 'Technology', 'Design' ),
		'tags'       => array( 'Interview', 'News' ),
		'excerpt'    => 'We talked to a developer who maintains a popular block theme about theme.json, backwards compatibility and what users actually ask for.',
		'body'       => array(
			'Maintaining a block theme means supporting sites built on every version you have ever shipped. The hardest part, the maintainer told us, is changing a default without changing someone\'s homepage.',
			'Most feature requests are about control: another font size, another spacing option. The answer is usually a better preset rather than a new setting.',
			'Asked for one piece of advice, the answer was immediate: read the support forum every week. The questions tell you what the documentation missed.',
		),
	),
	array(
		'title'      => 'Performance Budgets for Content Teams',
		'categories' => array( 'Technology' ),
		'tags'       => array( 'Guide', 'Deep Dive' ),
		'excerpt'    => 'A performance budget only works if the people adding images and embeds know it exists. Make the limits visible where content is written.',
		'body'       => array(
			'Developers set performance budgets, but content teams spend them. A single uncompressed hero image or third-party embed can undo months of optimisation.',
			'Put the budget where editors can see it: image size limits in the media library, a short list of approved embeds, and a page weight check before publishing.',
			'Measure the pages people actually visit, on the devices they actually use. A fast demo page on office Wi-Fi proves very little.',
		),
	),
	array(
		'title'      => 'Color Systems That Survive a Rebrand',
		'categories' => array( 'Design' ),
		'tags'       => array( 'Deep Dive', 'Trends', 'Guide' ),
		'excerpt'    => 'Brand colours change every few years. A colour system built on roles instead of hex codes lets you swap the palette without redesigning every component.',
		'body'       => array(
			'Components should not know that the brand colour is purple. They should know that a button uses the accent colour and that text uses the ink colour.',
			'Build one neutral scale for text, borders and surfaces, and one accent for the few things that need attention. Most interfaces need far less colour than a brand guide provides.',
			'When the rebrand arrives, you change the values behind the roles and check contrast. The components stay exactly as they are.',
		),
	),
	array(
		'title'      => 'The Economics of Open Source Plugins',
		'categories' => array( 'Business' ),
		'tags'       => array( 'Deep Dive', 'Opinion', 'News' ),
		'excerpt'    => 'Millions of sites run on plugins maintained by a handful of people. Support, security and upgrades cost money even when the download is free.',
		'body'       => array(
			'A free plugin is not free to maintain. Every WordPress release, PHP version and security report needs someone\'s time, often unpaid.',
			'The plugins that last usually find a model that pays for that time: premium add-ons, paid support or sponsorship from companies that depend on them.',
			'For site owners, the lesson is to treat critical plugins like any other supplier. Check who maintains them, how often they update, and what happens if they stop.',
		),
	),
	array(
		'title'      => 'Reading the Room: Culture Shifts in Remote Teams',
		'categories' => array( 'Culture' ),
		'tags'       => array( 'Trends', 'Interview', 'News' ),
		'excerpt'    => 'Remote teams lose the hallway conversations that used to spread context. The teams that adapt make that context deliberate and written.',
		'body'       => array(
			'In an office, people absorb context without trying: who is stressed, which project is slipping, what the manager cares about this week. Remote work removes most of those signals.',
			'The teams that adapt replace them on purpose. Short written updates, open channels instead of private messages, and regular unstructured time together.',
			'We asked several team leads what surprised them most. The common answer was that quiet people contributed more once decisions were made in writing.',
		),
	),
);
