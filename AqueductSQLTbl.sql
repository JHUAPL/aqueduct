create table aqueduct 
(
	aq_wiki_namespace 	varchar(100) 	primary key, 
	aq_wiki_namespace_id 	int 		unique, 
	aq_source_uri 		varchar(100) 	unique, 
	aq_source_type 		varchar(10), 
	aq_source_name		varchar(100),
	aq_source_location 	varchar(100), 
	aq_source_cert_path 	varchar(100),
	aq_source_cert_pass	varchar(100),
	aq_initial_lowercase int,
	aq_search_fragments int
);

create table aqueductqueries
(
	aq_wiki_namespace_id 	int 		primary key, 
	aq_wiki_parent_namespace varchar(100) 	references aqueduct(aq_wiki_namespace),
	aq_wiki_namespace_tag 	varchar(100), 
	aq_query_type 		varchar(100),
	aq_datasource 		varchar(100),
	aq_query		varchar(2000),
	aq_algorithm 		varchar(100),
	aq_query_uri_param 	int
);


create table aqueductoldnamespaces
(
	aq_wiki_namespace_id 	int 		primary key, 
	aq_wiki_namespace 	varchar(100) 	unique
);
