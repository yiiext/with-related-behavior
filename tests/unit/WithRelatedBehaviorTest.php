<?php
class WithRelatedBehaviorTest extends CDbTestCase
{
	public $fixtures=array(
		'articles'=>'Article',
		'comments'=>'Comment',
		'groups'=>'Group',
		'tags'=>'Tag',
		'users'=>'User',
		':article_tag',
	);

	public function testValidate()
	{
		$article=new Article;
		$this->assertFalse($article->withRelated->validate());

		$article->title='Test';
		$this->assertTrue($article->withRelated->validate());

		$comment1=new Comment;
		$comment2=new Comment;
		$comment3=new Comment;

		$article->comments=array($comment1,$comment2,$comment3);
		$this->assertFalse($article->withRelated->validate(array('comments')));

		$comment1->content='Test';
		$comment2->content='Test';
		$comment3->content='Test';
		$this->assertTrue($article->withRelated->validate(array('comments')));
		$this->assertTrue($article->withRelated->validate(array('comments'=>array('content'))));

		$article->createdBy=new User;
		$this->assertFalse($article->withRelated->validate(array('comments','createdBy')));

		$article->createdBy->name='Test';
		$this->assertTrue($article->withRelated->validate(array('comments','createdBy')));
		$this->assertTrue($article->withRelated->validate(array('comments'=>array('content'),'createdBy'=>array('name'))));

		$article->createdBy->group=new Group;
		$this->assertFalse($article->withRelated->validate(array('comments','createdBy'=>array('group'))));

		$article->createdBy->group->name='Test';
		$this->assertTrue($article->withRelated->validate(array('comments','createdBy'=>array('group'))));
		$this->assertTrue($article->withRelated->validate(array('comments'=>array('content'),'createdBy'=>array('name','group'=>array('name')))));
	}

	public function testInsert()
	{
		$user=new User;
		$user->id=100;
		$user->name='Test';

		$user->group=new Group;
		$user->group->id=100;
		$user->group->name='Test';

		$tag1=new Tag;
		$tag1->createdBy=$user;
		$tag1->name='test1';
		$tag2=new Tag;
		$tag2->createdBy=$user;
		$tag2->name='test2';
		$tag3=new Tag;
		$tag3->createdBy=$user;
		$tag3->name='test3';

		$article=new Article;
		$article->id=100;
		$article->title='Test';
		$article->tags=array($tag1,$tag2,$tag3);

		$comment1=new Comment;
		$comment1->id=100;
		$comment1->content='Test1';
		$comment2=new Comment;
		$comment2->id=101;
		$comment2->content='Test2';
		$comment3=new Comment;
		$comment3->id=102;
		$comment3->content='Test3';

		$article->comments=array($comment1,$comment2,$comment3);
		$article->createdBy=$user;
		$article->withRelated->insert(array('comments','tags'=>array('createdBy'),'createdBy'=>array('id','group_id','name','group'=>array('id','name'))));
	}

	public function testUpdateModelInsertRelated()
	{
		$article=Article::model()->findByPk(1);
		$article->title='article1 updated';

		$tag3=new Tag;
		$tag3->id=3;
		$tag3->created_by_id=1;
		$tag3->name='tag3';
		$tag4=new Tag;
		$tag4->id=4;
		$tag4->created_by_id=1;
		$tag4->name='tag4';

		$article->tags=array($tag3,$tag4);
		$article->withRelated->update(array('tags'));
	}

	public function testUpdateModelUpdateRelated()
	{
		$article=Article::model()->with('tags')->findByPk(1);
		$article->title='article1 updated';

		$tags=$article->tags;
		$tags[0]->name='tag1 updated';
		$tags[1]->name='tag2 updated';

		$article->tags=$tags;
		$article->withRelated->update(array('tags'));
	}

	public function testUpdateModelDeleteRelated()
	{
		$article=Article::model()->with('tags')->findByPk(1);
		$article->title='article1 updated';

		$article->tags=array();

		$article->withRelated->update(array('tags'));
	}
}