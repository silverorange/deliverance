create table MailingListCampaignSegment (
	id serial,

	shortname varchar(255) not null,
	title varchar(255) not null,
	displayorder integer not null default 0,
	segment_options text,

	primary key (id)
);

create index MailingListCampaignSegment_shortname_index
	on MailingListCampaignSegment(shortname);