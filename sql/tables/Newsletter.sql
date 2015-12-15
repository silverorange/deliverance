create table Newsletter (
	id serial,

	preheader varchar(100),
	subject varchar(255),
	html_content text,
	text_content text,

	template integer default null references NewsletterTemplate(id),
	custom_template varchar(50),
	campaign_segment integer default null references MailingListCampaignSegment(id),
	campaign_id varchar(255),
	campaign_report_url varchar(255),

	google_campaign varchar(30),

	send_date timestamp,
	createdate timestamp,

	instance integer references Instance(id) on delete cascade,

	primary key (id)
);
