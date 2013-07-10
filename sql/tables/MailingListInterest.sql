create table MailingListInterest (
	id serial,

	shortname varchar(255) not null,
	group_shortname varchar(255) not null,
	title varchar(255) not null,
	displayorder integer not null default 0,
	visible boolean not null default true,

	instance integer references Instance(id) on delete cascade,

	primary key (id)
);

create index MailingListInterest_shortname_index
	on MailingListInterest(shortname);