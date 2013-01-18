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

	public function testSave()
	{
		$article=new Article;

		$user=new User;

		$article->user=$user;

		$user->group=new Group;

		$comment1=new Comment;
		$comment1->user=$user;

		$comment2=new Comment;
		$comment2->user=$user;

		$article->comments=array($comment1,$comment2);

		$tag1=new Tag;
		$tag2=new Tag;

		$article->tags=array($tag1,$tag2);

		$result=$article->withRelated->save(true,array(
			'user'=>array('group'),
			'comments'=>array('user'),
			'tags',
		));

		$this->assertFalse($result);

		$article->title='article1';
		$user->name='user1';
		$user->group->name='group1';
		$comment1->content='comment1';
		$comment2->content='comment2';
		$tag1->name='tag1';
		$tag2->name='tag2';

		$result=$article->withRelated->save(true,array(
			'user'=>array('group'),
			'comments'=>array('user'),
			'tags',
		));

		$this->assertTrue($result);

		$article=Article::model()->with(array(
			'user'=>array(
				'with'=>'group',
				'alias'=>'article_user',
			),
			'comments'=>array(
				'with'=>array(
					'user'=>array(
						'alias'=>'comment_user',
					),
				),
			),
			'tags',
		))->find();

		$this->assertNotNull($article);
		$this->assertEquals('article1',$article->title);
		$this->assertNotNull($article->user);
		$this->assertEquals('user1',$article->user->name);
		$this->assertNotNull($article->user->group);
		$this->assertEquals('group1',$article->user->group->name);
		$this->assertEquals(2,count($article->comments));
		$this->assertEquals('comment1',$article->comments[0]->content);
		$this->assertEquals('comment2',$article->comments[1]->content);
		$this->assertNotNull($article->comments[0]->user);
		$this->assertEquals('user1',$article->comments[0]->user->name);
		$this->assertNotNull($article->comments[1]->user);
		$this->assertEquals('user1',$article->comments[1]->user->name);
		$this->assertEquals(2,count($article->tags));
		$this->assertEquals('tag1',$article->tags[0]->name);
		$this->assertEquals('tag2',$article->tags[1]->name);

		$article=Article::model()->with('comments')->find();

		$comments=$article->comments;
		$comments[0]->content='comment1 update';
		$comments[1]->content='comment2 update';

		$comment=new Comment;
		$comment->user=$user;
		$comment->content='comment3';

		$comments[]=$comment;

		$comment=new Comment;
		$comment->user=$user;
		$comment->content='comment4';

		$comments[]=$comment;

		$article->comments=$comments;

		$result=$article->withRelated->save(true,array('comments'=>array('user')));
		$this->assertTrue($result);

		$article=Article::model()->with('comments')->find();
		$this->assertEquals(4,count($article->comments));
		$this->assertEquals('comment1 update',$article->comments[0]->content);
		$this->assertEquals('comment2 update',$article->comments[1]->content);
		$this->assertEquals('comment3',$article->comments[2]->content);
		$this->assertEquals('comment4',$article->comments[3]->content);

		$article=Article::model()->with('tags')->find();

		$tags=$article->tags;
		$tags[0]->name='tag1 update';
		$tags[1]->name='tag2 update';

		$tag=new Tag;
		$tag->name='tag3';

		$tags[]=$tag;

		$tag=new Tag;
		$tag->name='tag4';

		$tags[]=$tag;

		$article->tags=$tags;

		$result=$article->withRelated->save(true,array('tags'));
		$this->assertTrue($result);

		$article=Article::model()->with('tags')->find();
		$this->assertEquals(4,count($article->tags));
		$this->assertEquals('tag1 update',$article->tags[0]->name);
		$this->assertEquals('tag2 update',$article->tags[1]->name);
		$this->assertEquals('tag3',$article->tags[2]->name);
		$this->assertEquals('tag4',$article->tags[3]->name);
	}

	/**
	 * Tests non-database class attributes validation.
	 * User::$firstName and User::$lastName attributes validation will be tested.
	 */
	public function testClassAttributesValidation()
	{
		// 1. validate all models

		// validation will fail because 'Torvalds' string is too long
		$user1=new User('scenario');
		$user1->group=new Group();
		$user1->group->name='Linux Kernel Team';
		$user1->attributes=array('firstName'=>'Linus1','lastName'=>'Torvalds','name'=>'ignoredString');
		$result=$user1->withRelated->save(true,array('group','firstName','lastName','name','group_id'));

		$this->assertFalse($result);
		$this->assertEmpty($user1->group->errors);
		$this->assertNotEmpty($user1->errors);
		$this->assertFalse(User::model()->exists('name="Linus1 Torvalds"'));

		// validation will be successful because 'Morton' string is short enough
		$user2=new User('scenario');
		$user2->group=new Group();
		$user2->group->name='Linux Kernel Team';
		$user2->attributes=array('firstName'=>'Andrew1','lastName'=>'Morton','name'=>'ignoredString');
		$result=$user2->withRelated->save(true,array('group','firstName','lastName','name','group_id'));

		$this->assertTrue($result);
		$this->assertEmpty($user2->group->errors);
		$this->assertEmpty($user2->errors);
		$this->assertTrue(User::model()->exists('name="Andrew1 Morton"'));

		// 2. skip models validation

		// validation will be successful because 'Torvalds' string is not validated
		// note: validation disabled by passing false value as first argument to the WithRelatedBehavior::save() method
		$user3=new User('scenario');
		$user3->group=new Group();
		$user3->group->name='Linux Kernel Team';
		$user3->attributes=array('firstName'=>'Linus2','lastName'=>'Torvalds','name'=>'ignoredString');
		$result=$user3->withRelated->save(false,array('group','firstName','lastName','name','group_id'));

		$this->assertTrue($result);
		$this->assertEmpty($user3->group->errors);
		$this->assertEmpty($user3->errors);
		$this->assertTrue(User::model()->exists('name="Linus2 Torvalds"'));

		// validation will be successful because 'Morton' string is not validated
		// note: validation disabled by passing false value as first argument to the WithRelatedBehavior::save() method
		$user4=new User('scenario');
		$user4->group=new Group();
		$user4->group->name='Linux Kernel Team';
		$user4->attributes=array('firstName'=>'Andrew2','lastName'=>'Morton','name'=>'ignoredString');
		$result=$user4->withRelated->save(false,array('group','firstName','lastName','name','group_id'));

		$this->assertTrue($result);
		$this->assertEmpty($user4->group->errors);
		$this->assertEmpty($user4->errors);
		$this->assertTrue(User::model()->exists('name="Andrew2 Morton"'));

		// 3. validate all models but don't validate class attributes

		// validation will be successful because 'Torvalds' string is not validated
		// note: 'Linus3 Torvalds' string saved to the database since $firstName and $lastName attributes are safe
		$user5=new User('scenario');
		$user5->group=new Group();
		$user5->group->name='Linux Kernel Team';
		$user5->attributes=array('firstName'=>'Linus3','lastName'=>'Torvalds','name'=>'ignoredString');
		$result=$user5->withRelated->save(true,array('group','name','group_id'));

		$this->assertTrue($result);
		$this->assertEmpty($user5->group->errors);
		$this->assertEmpty($user5->errors);
		$this->assertTrue(User::model()->exists('name="Linus3 Torvalds"'));

		// validation will be successful because 'Morton' string is not validated
		// note: 'Andrew3 Morton' string saved to the database since $firstName and $lastName attributes are safe
		$user6=new User('scenario');
		$user6->group=new Group();
		$user6->group->name='Linux Kernel Team';
		$user6->attributes=array('firstName'=>'Andrew3','lastName'=>'Morton','name'=>'ignoredString');
		$result=$user6->withRelated->save(true,array('group','name','group_id'));

		$this->assertTrue($result);
		$this->assertEmpty($user6->group->errors);
		$this->assertEmpty($user6->errors);
		$this->assertTrue(User::model()->exists('name="Andrew3 Morton"'));

		// 4. validate real class attributes validation of the related models

		// validation will fail because 'Linux Kernel Team' string is too long to pass Group model validation rules
		$user7=new User('scenario');
		$user7->group=new Group('scenario');
		$user7->group->attributes=array('otherName'=>'Linux Kernel Team','name'=>'ignoredString');
		$user7->attributes=array('firstName'=>'Alan1','lastName'=>'Cox','name'=>'ignoredString');
		$result=$user7->withRelated->save(true,array('group'=>array('otherName','name'),'firstName','lastName','name','group_id'));

		$this->assertFalse($result);
		$this->assertNotEmpty($user7->group->errors);
		$this->assertEmpty($user7->errors);
		$this->assertFalse(User::model()->exists('name="Alan1 Cox"'));

		// validation will be successful because 'Linux' string is short enough to fit Group model validation rules
		$user8=new User('scenario');
		$user8->group=new Group('scenario');
		$user8->group->attributes=array('otherName'=>'Linux','name'=>'ignoredString');
		$user8->attributes=array('firstName'=>'Alan2','lastName'=>'Cox','name'=>'ignoredString');
		$result=$user8->withRelated->save(true,array('group'=>array('otherName','name'),'firstName','lastName','name','group_id'));

		$this->assertTrue($result);
		$this->assertEmpty($user8->group->errors);
		$this->assertEmpty($user8->errors);
		$this->assertTrue(User::model()->exists('name="Alan2 Cox"'));
	}
}