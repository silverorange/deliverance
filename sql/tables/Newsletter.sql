create table Newsletter (
	id serial,

	preheader varchar(100),
	subject varchar(255),
	html_content text,
	text_content text,

	campaign_segment integer default null references MailingListCampaignSegment(id),
	campaign_id varchar(255),
	campaign_report_url varchar(255),

	send_date timestamp,
	createdate timestamp,

	instance integer references Instance(id) on delete cascade,

	primary key (id)
);