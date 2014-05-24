<?php if (!defined('APPLICATION'))
    exit();

/**
 * The controller for the composing of articles.
 */
class ComposeController extends Gdn_Controller {
    /**
     * Models to include.
     */
    public $Uses = array('ArticleModel', 'ArticleCategoryModel', 'Form');

    protected $Article = false;
    protected $Category = false;

    /**
     * Include JS, CSS, and modules used by all methods.
     * Extended by all other controllers in this application.
     * Always called by dispatcher before controller's requested method.
     */
    public function Initialize() {
        // Set up head.
        $this->Head = new HeadModule($this);

        // Add JS files.
        $this->AddJsFile('jquery.js');
        $this->AddJsFile('jquery.livequery.js');
        $this->AddJsFile('jquery.form.js');
        $this->AddJsFile('jquery.popup.js');
        $this->AddJsFile('jquery.gardenhandleajaxform.js');
        $this->AddJsFile('jquery.autogrow.js');
        $this->AddJsFile('jquery.autocomplete.js');
        $this->AddJsFile('global.js');
        $this->AddJsFile('articles.js');

        // Add CSS files.
        $this->AddCssFile('style.css');
        $this->AddCssFile('articles.css');

        // Add modules.
        $this->AddModule('GuestModule');
        $this->AddModule('SignedInModule');

        parent::Initialize();
    }

    /**
     * This handles the articles dashboard.
     * Only visible to users that have permission.
     */
    public function Index() {
        $this->Title(T('Articles Dashboard'));

        // Set allowed permissions.
        // The user only needs one of the specified permissions.
        $PermissionsAllowed = array('Articles.Articles.Add', 'Articles.Articles.Edit');
        $this->Permission($PermissionsAllowed, false);

        // Get recently published articles.
        $RecentlyPublishedOffset = 0;
        $RecentlyPublishedLimit = 5;
        $RecentlyPublishedWheres = array('a.Status' => ArticleModel::STATUS_PUBLISHED);
        $RecentlyPublished = $this->ArticleModel->Get($RecentlyPublishedOffset, $RecentlyPublishedLimit,
            $RecentlyPublishedWheres);
        $this->SetData('RecentlyPublished', $RecentlyPublished);

        // Get recent articles pending review.
        if (Gdn::Session()->CheckPermission('Articles.Articles.Edit')) {
            $PendingArticlesOffset = 0;
            $PendingArticlesLimit = 5;
            $PendingArticlesWheres = array('a.Status' => ArticleModel::STATUS_PENDING);
            $PendingArticles = $this->ArticleModel->Get($PendingArticlesOffset, $PendingArticlesLimit,
                $PendingArticlesWheres);
            $this->SetData('PendingArticles', $PendingArticles);
        }

        $this->View = 'index';
        $this->Render();
    }

    public function Posts($Page = false) {
        $this->Title(T('Article Posts'));

        // Set allowed permissions.
        // The user only needs one of the specified permissions.
        $PermissionsAllowed = array('Articles.Articles.Add', 'Articles.Articles.Edit');
        $this->Permission($PermissionsAllowed, false);

        // Get total article count.
        $CountArticles = $this->ArticleModel->GetCount();
        $this->SetData('CountArticles', $CountArticles);

        // Determine offset from $Page.
        list($Offset, $Limit) = OffsetLimit($Page, C('Articles.Articles.PerPage', 12));
        $Page = PageNumber($Offset, $Limit);
        $this->CanonicalUrl(Url(ConcatSep('/', 'articles', PageNumber($Offset, $Limit, true, false)), true));

        // Have a way to limit the number of pages on large databases
        // because requesting a super-high page can kill the db.
        $MaxPages = C('Articles.Articles.MaxPages', false);
        if ($MaxPages && $Page > $MaxPages) {
            throw NotFoundException();
        }

        // Build a pager
        $PagerFactory = new Gdn_PagerFactory();
        $this->EventArguments['PagerType'] = 'Pager';
        $this->FireEvent('BeforeBuildPager');
        $this->Pager = $PagerFactory->GetPager($this->EventArguments['PagerType'], $this);
        $this->Pager->ClientID = 'Pager';
        $this->Pager->Configure($Offset, $Limit, $CountArticles, 'articles/%1$s');
        if (!$this->Data('_PagerUrl'))
            $this->SetData('_PagerUrl', 'articles/{Page}');
        $this->SetData('_Page', $Page);
        $this->SetData('_Limit', $Limit);
        $this->FireEvent('AfterBuildPager');

        // If the user is not an article editor, then only show their own articles.
        $Session = Gdn::Session();
        $Wheres = false;
        if (!$Session->CheckPermission('Articles.Articles.Edit'))
            $Wheres = array('a.AuthorUserID' => $Session->UserID);

        // Get the articles.
        $Articles = $this->ArticleModel->Get($Offset, $Limit, $Wheres);
        $this->SetData('Articles', $Articles);

        $this->View = 'posts';
        $this->Render();
    }

    private function GetArticleStatusOptions() {
        $StatusOptions = array(
            ArticleModel::STATUS_DRAFT => T('Draft'),
            ArticleModel::STATUS_PENDING => T('Pending Review'),
        );

        if (Gdn::Session()->CheckPermission('Articles.Articles.Edit')
            || ($this->Article && ((int)$this->Article->Status == 2))
        )
            $StatusOptions[ArticleModel::STATUS_PUBLISHED] = T('Published');

        return $StatusOptions;
    }

    public function Article() {
        // If not editing...
        if (!$this->Article) {
            $this->Title(T('Add Article'));

            // Set allowed permission.
            $this->Permission('Articles.Articles.Add');
        }

        // Set the model on the form.
        $this->Form->SetModel($this->ArticleModel);

        // Get categories.
        $Categories = $this->ArticleCategoryModel->Get();
        $this->SetData('Categories', $Categories, true);

        // Set status options.
        $this->SetData('StatusOptions', $this->GetArticleStatusOptions(), true);

        $UserModel = new UserModel();

        // The form has not been submitted yet.
        if (!$this->Form->AuthenticatedPostBack()) {
            // If editing...
            if ($this->Article) {
                $this->Form->SetData($this->Article);

                $this->Form->AddHidden('UrlCodeIsDefined', '1');

                // Set author field.
                $Author = $UserModel->GetID($this->Article->AuthorUserID);

                // If the user with AuthorUserID doesn't exist.
                if (!$Author)
                    $Author = $UserModel->GetID($this->Article->InsertUserID);
            } else {
                // If not editing...
                $this->Form->AddHidden('UrlCodeIsDefined', '0');
            }

            // If the user with InsertUserID doesn't exist.
            if (!$Author)
                $Author = Gdn::Session()->User;

            $this->Form->SetValue('AuthorUserName', $Author->Name);
        } else { // The form has been submitted.
            // Manually validate certain fields.
            $FormValues = $this->Form->FormValues();

            // Validate the URL code.
            // Set UrlCode to name of article if it's not defined.
            if ($FormValues['UrlCode'] == '')
                $FormValues['UrlCode'] = $FormValues['Name'];

            // Format the UrlCode.
            $FormValues['UrlCode'] = Gdn_Format::Url($FormValues['UrlCode']);
            $this->Form->SetFormValue('UrlCode', $FormValues['UrlCode']);

            // If editing, make sure the ArticleID is passed to the form save method.
            $SQL = Gdn::Database()->SQL();
            if ($this->Article) {
                $this->Form->SetFormValue('ArticleID', (int)$this->Article->ArticleID);

                $ValidUrlCode = $SQL
                    ->Select('a.UrlCode')
                    ->From('Article a')
                    ->Where('a.ArticleID', $this->Article->ArticleID)
                    ->Get()
                    ->FirstRow();
            }

            // Make sure that the UrlCode is unique among articles.
            $UrlCodeExists = $SQL
                ->Select('a.ArticleID')
                ->From('Article a')
                ->Where('a.UrlCode', $FormValues['UrlCode'])
                ->Get()
                ->NumRows();

            if ((isset($this->Article) && $UrlCodeExists && ($ValidUrlCode->UrlCode != $FormValues['UrlCode']))
                || ((!isset($this->Article) && $UrlCodeExists))
            )
                $this->Form->AddError('The specified URL code is already in use by another article.', 'UrlCode');

            // Retrieve author user ID.
            $Author = $UserModel->GetByUsername($FormValues['AuthorUserName']);

            // If the inputted author doesn't exist.
            if (!$Author) {
                if ($FormValues['AuthorUserName'] === "")
                    $this->Form->AddError('Author is required.', 'AuthorUserName');
                else
                    $this->Form->AddError('The user for the author field does not exist.', 'AuthorUserName');
            }

            $this->Form->SetFormValue('AuthorUserID', (int)$Author->UserID);

            if ($this->Form->ErrorCount() == 0) {
                $ArticleID = $this->Form->Save($FormValues);

                // If the article was saved successfully.
                if ($ArticleID) {
                    $Article = $this->ArticleModel->GetByID($ArticleID);

                    // Redirect to the article.
                    if ($this->_DeliveryType == DELIVERY_TYPE_ALL)
                        Redirect(ArticleUrl($Article));
                    else
                        $this->RedirectUrl = ArticleUrl($Article, '', true);
                }
            }

            // TODO add/edit article validation and more fields.
        }

        $this->View = 'article';
        $this->Render();
    }

    public function Comment() {
        $this->Title(T('Post Article Comment'));

        // Set required permission.
        $this->Permission('Articles.Comments.Add');

        // Set the model on the form.
        $this->Form->SetModel($this->ArticleModel);

        $this->View = 'comment';
        $this->Render();
    }

    public function EditArticle($ArticleID = false) {
        $this->Title(T('Edit Article'));

        // Set allowed permission.
        $this->Permission('Articles.Articles.Edit');

        // Get article.
        if (is_numeric($ArticleID))
            $this->Article = $this->ArticleModel->GetByID($ArticleID);

        // If the article doesn't exist, then throw an exception.
        if (!$this->Article)
            throw NotFoundException('Article');

        // Get category.
        $this->Category = $this->ArticleCategoryModel->GetByID($this->Article->CategoryID);

        $this->View = 'article';
        $this->Article();
    }

    public function CloseArticle() {
        // TODO CloseArticle()
    }

    public function DeleteArticle() {
        // TODO DeleteArticle()
    }
}
