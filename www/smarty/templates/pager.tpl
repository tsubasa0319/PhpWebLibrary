<section {attributes items=[
    'id'    => $id|default:false,
    'class' => ['pager', $class|default:false]
]}>
    <div>{include file='pagerPrev.tpl' table=$table name=$prev.name|default:''
        text=$prev.text|default:''}</div>
    <div>{include file='pagerPage.tpl' table=$table}</div>
    <div>{include file='pagerNext.tpl' table=$table name=$next.name|default:''
        text=$next.text|default:''}</div>
</section>