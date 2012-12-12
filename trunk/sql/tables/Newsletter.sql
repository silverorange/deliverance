create table Newsletter (
	id serial,

	subject varchar(255),
	html_content text,
	text_content text,

	campaign_segment integer default null references MailingListCampaignSegment(id),
	campaign_id varchar(255),
	campaign_report_url varchar(255),

	send_date timestamp,
	createdate timestamp,

	primary key (id)
);
