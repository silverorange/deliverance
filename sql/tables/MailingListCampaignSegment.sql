create table MailingListCampaignSegment (
	id serial,

	title varchar(255) not null,
	displayorder integer not null default 0,
	rules text,

	primary key (id)
);