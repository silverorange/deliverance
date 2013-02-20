create table MailingListUpdateQueue (
	id serial,
	email varchar(255) not null,
	info text,
	primary key (id)
);

create index MailingListUpdateQueue_email_index on MailingListUpdateQueue(email);
