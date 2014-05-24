<?php if (!defined('APPLICATION'))
    exit();

/**
 * The class.hooks.php file is essentially a giant plugin container for an app
 * that is automatically enabled when this app is.
 */
class ArticlesHooks extends Gdn_Plugin {
    /**
     * Add link to the articles controller in the main menu.
     *
     * @param $Sender
     */
    public function Base_Render_Before($Sender) {
        if ($Sender->Menu)
            $Sender->Menu->AddLink('Articles', T('Articles'), '/articles/');
    }

    /**
     * Automatically executed when this application is enabled.
     */
    public function Setup() {
        // Initialize variables that are used for the structuring and stub inserts.
        $Database = Gdn::Database();
        $SQL = $Database->SQL();
        $Drop = false; // Gdn::Config('Articles.Version') === FALSE ? TRUE : FALSE;
        $Explicit = true;

        // Call structure.php to update database.
        include(PATH_APPLICATIONS . DS . 'articles' . DS . 'settings' . DS . 'structure.php');
        include(PATH_APPLICATIONS . DS . 'articles' . DS . 'settings' . DS . 'stub.php');

        // Save version number to config.
        $ApplicationInfo = array();
        include(PATH_APPLICATIONS . DS . 'articles' . DS . 'settings' . DS . 'about.php');
        $Version = ArrayValue('Version', $ApplicationInfo['Articles'], false);
        if ($Version) {
            $Save = array('Articles.Version' => $Version);
            SaveToConfig($Save);
        }
    }

    /**
     * Create the Articles settings page.
     * Runs the Dispatch method which handles methods for the page.
     *
     * @param SettingsController $Sender
     */
    public function SettingsController_Articles_Create($Sender) {
        $this->Dispatch($Sender, $Sender->RequestArgs);
    }

    /**
     * The Index method of the Articles setting page.
     *
     * @param SettingsController $Sender
     */
    public function Controller_Index($Sender) {
        $Sender->Title('Articles Settings');

        // Set required permission.
        $Sender->Permission('Garden.Settings.Manage');

        // Set up the configuration module.
        $ConfigModule = new ConfigurationModule($Sender);

        $ConfigModule->Initialize(array(
            //'Example.Example.Enabled' => array(
            //   'LabelCode' => 'Use Example',
            //   'Control'   => 'Checkbox'
            //)
        ));

        $Sender->ConfigurationModule = $ConfigModule;

        $Sender->AddSideMenu('/settings/articles/');
        $ConfigModule->RenderAll();
    }

    /**
     * The Categories method of the Articles setting page.
     *
     * @param SettingsController $Sender
     */
    public function Controller_Categories($Sender) {
        $Sender->Title('Article Categories');

        // Set required permission.
        $Sender->Permission('Garden.Settings.Manage');

        // Add assets.
        $Sender->AddJsFile('js/library/nestedSortable.1.3.4/jquery-ui-1.8.11.custom.min.js');
        $Sender->AddJsFile('js/library/nestedSortable.1.3.4/jquery.ui.nestedSortable.js');

        // Set up the article category model.
        $ArticleCategoryModel = new ArticleCategoryModel();

        $Categories = $ArticleCategoryModel->Get();
        $Sender->SetData('Categories', $Categories, true);

        $Sender->AddSideMenu('/settings/articles/categories/');
        $Sender->View = $Sender->FetchViewLocation('categories', 'settings', 'articles');
        $Sender->Render();
    }

    /**
     * The Categories method of the Articles setting page.
     *
     * @param SettingsController $Sender
     */
    public function Controller_AddCategory($Sender) {
        // Set required permission.
        $Sender->Permission('Garden.Settings.Manage');

        // Add asset.
        $Sender->AddJsFile('articles.js', 'articles');

        // Set up the article category model.
        $Sender->Form = new Gdn_Form();
        $ArticleCategoryModel = new ArticleCategoryModel();
        $Sender->Form->SetModel($ArticleCategoryModel);

        // If editing a category, then set the data in the form.
        $Category = false;
        if ($Sender->RequestArgs[0] === 'editcategory') {
            $CategoryID = (int)$Sender->RequestArgs[1];

            if (is_numeric($CategoryID)) {
                $Category = $ArticleCategoryModel->GetByID($CategoryID);

                if ($Category)
                    $Sender->Form->SetData($Category);
                else
                    throw NotFoundException(T('Article category'));
            } else {
                throw NotFoundException(T('Article category'));
            }
        }

        // Set the title of the page.
        if (!$Category)
            $Sender->Title(T('Add Article Category'));
        else
            $Sender->Title(T('Edit Article Category'));

        // Handle the form.
        if (!$Sender->Form->AuthenticatedPostBack()) {
            if (!$Category)
                $Sender->Form->AddHidden('UrlCodeIsDefined', '0');
            else
                $Sender->Form->AddHidden('UrlCodeIsDefined', '1');
        } else { // The form was saved.
            // Define some validation rules for the fields being saved.
            $Sender->Form->ValidateRule('Name', 'function:ValidateRequired');
            $Sender->Form->ValidateRule('UrlCode', 'function:ValidateRequired', T('URL code is required.'));

            // Manually validate certain fields.
            $FormValues = $Sender->Form->FormValues();

            if ($Category)
                $FormValues['CategoryID'] = $CategoryID;

            // Format URL code before saving.
            $FormValues['UrlCode'] = Gdn_Format::Url($FormValues['UrlCode']);

            // Check if URL code is in use by another category.
            $CategoryWithNewUrlCode = (bool)$ArticleCategoryModel->GetByUrlCode($FormValues['UrlCode']);
            if ((!$Category && $CategoryWithNewUrlCode)
                || ($Category && $CategoryWithNewUrlCode && ($Category->UrlCode != $FormValues['UrlCode']))
            )
                $Sender->Form->AddError('The specified URL code is already in use by another category.', 'UrlCode');

            // If there are no errors, then save the category.
            if ($Sender->Form->ErrorCount() == 0) {
                if ($Sender->Form->Save($FormValues)) {
                    if (!$Category) {
                        // Inserting.
                        $Sender->RedirectUrl = Url('/settings/articles/categories/');
                        $Sender->InformMessage(T('New article category added successfully.'));
                    } else {
                        // Editing.
                        $Sender->InformMessage(T('The article category has been saved successfully.'));
                    }
                }
            }
        }

        $Sender->AddSideMenu('/settings/articles/categories/');
        $Sender->View = $Sender->FetchViewLocation('addcategory', 'settings', 'articles');
        $Sender->Render();
    }

    /**
     * @param SettingsController $Sender
     */
    public function Controller_EditCategory($Sender) {
        $this->Controller_AddCategory($Sender);
    }

    /**
     * @param SettingsController $Sender
     */
    public function Controller_DeleteCategory($Sender) {
        // Check permission.
        $Sender->Permission('Garden.Settings.Manage');

        // Set up head.
        $Sender->Title(T('Delete Article Category'));
        $Sender->AddSideMenu('/settings/articles/categories/');
        $Sender->AddJsFile('articles.js', 'articles');

        // Get category ID.
        $CategoryID = false;
        if (isset($Sender->RequestArgs[1]) && is_numeric($Sender->RequestArgs[1]))
            $CategoryID = $Sender->RequestArgs[1];

        // Get category data.
        $Sender->Form = new Gdn_Form();
        $ArticleCategoryModel = new ArticleCategoryModel();
        $Category = $ArticleCategoryModel->GetByID($CategoryID);
        $Sender->SetData('Category', $Category, true);

        if (!$Category) {
            $Sender->Form->AddError('The specified article category could not be found.');
        } else {
            // Make sure the form knows which item we are deleting.
            $Sender->Form->AddHidden('CategoryID', $CategoryID);

            // Get a list of categories other than this one that can act as a replacement.
            $OtherCategories = $ArticleCategoryModel->Get(array(
                'CategoryID <>' => $CategoryID,
                'CategoryID >' => 0
            ));
            $Sender->SetData('OtherCategories', $OtherCategories, true);

            if (!$Sender->Form->AuthenticatedPostBack()) {
                $Sender->Form->SetFormValue('DeleteArticles', '1'); // Checked by default
            } else {
                $ReplacementCategoryID = $Sender->Form->GetValue('ReplacementCategoryID');
                $ReplacementCategory = $ArticleCategoryModel->GetByID($ReplacementCategoryID);
                // Error if:
                // 1. The category being deleted is the last remaining category.
                if ($OtherCategories->NumRows() == 0)
                    $Sender->Form->AddError('You cannot remove the only remaining category.');

                if ($Sender->Form->ErrorCount() == 0) {
                    // Go ahead and delete the category.
                    try {
                        $ArticleCategoryModel->Delete($Category, $Sender->Form->GetValue('ReplacementCategoryID'));
                    } catch (Exception $ex) {
                        $Sender->Form->AddError($ex);
                    }

                    if ($Sender->Form->ErrorCount() == 0) {
                        $Sender->RedirectUrl = Url('/settings/articles/categories/');
                        $Sender->InformMessage(T('Deleting article category...'));
                    }
                }
            }
        }

        // Render default view.
        $Sender->View = $Sender->FetchViewLocation('deletecategory', 'settings', 'articles');
        $Sender->Render();
    }

    /**
     * Add links for the setting pages to the dashboard sidebar.
     *
     * @param Gdn_Controller $Sender
     */
    public function Base_GetAppSettingsMenuItems_Handler($Sender) {
        $GroupName = 'Articles';
        $Menu = & $Sender->EventArguments['SideMenu'];

        $Menu->AddItem($GroupName, $GroupName, false, array('class' => $GroupName));
        $Menu->AddLink($GroupName, T('Settings'), '/settings/articles/', 'Garden.Settings.Manage');
        $Menu->AddLink($GroupName, T('Categories'), '/settings/articles/categories/', 'Garden.Settings.Manage');
    }

    /**
     * Adds 'Articles' tab to profiles and adds CSS & JS files to their head.
     *
     * @param ProfileController $Sender
     */
    public function ProfileController_AddProfileTabs_Handler($Sender) {
        if (is_object($Sender->User) && ($Sender->User->UserID > 0)) {
            $UserID = $Sender->User->UserID;

            // Add the article tab
            $ArticlesLabel = Sprite('SpArticles') . ' ' . T('Articles');

            if (C('Articles.Profile.ShowCounts', true))
                $ArticlesLabel .= '<span class="Aside">' . CountString(GetValueR('User.CountArticles', $Sender,
                        null), "/profile/count/articles?userid=$UserID") . '</span>';

            $Sender->AddProfileTab(T('Articles'),
                'profile/articles/' . $Sender->User->UserID . '/' . rawurlencode($Sender->User->Name), 'Articles',
                $ArticlesLabel);

            // Add the article tab's CSS and Javascript.
            $Sender->AddJsFile('jquery.gardenmorepager.js');
            $Sender->AddJsFile('articles.js');
        }
    }

    /**
     * Creates virtual 'Articles' method in ProfileController.
     *
     * @param ProfileController $Sender
     */
    public function ProfileController_Articles_Create($Sender, $UserReference = '', $Username = '', $Page = '', $UserID = '') {
        $Sender->EditMode(false);

        // Tell the ProfileController what tab to load
        $Sender->GetUserInfo($UserReference, $Username, $UserID);
        $Sender->_SetBreadcrumbs(T('Articles'), '/profile/articles');
        $Sender->SetTabView('Articles', 'Articles', 'Profile', 'Articles');
        $Sender->CountCommentsPerPage = C('Articles.Articles.PerPage', 12);

        list($Offset, $Limit) = OffsetLimit($Page, Gdn::Config('Articles.Articles.PerPage', 12));

        $ArticleModel = new ArticleModel();
        $Articles = $ArticleModel->GetByUser($Sender->User->UserID, $Offset, $Limit,
            array('Status' => ArticleModel::STATUS_PUBLISHED));
        $CountArticles = $Offset + $ArticleModel->LastArticleCount + 1;
        $Sender->ArticleData = $Sender->SetData('Articles', $Articles);

        $Sender->ArticleCategoryModel = new ArticleCategoryModel();

        // Build a pager
        $PagerFactory = new Gdn_PagerFactory();
        $Sender->Pager = $PagerFactory->GetPager('MorePager', $Sender);
        $Sender->Pager->MoreCode = 'More Articles';
        $Sender->Pager->LessCode = 'Newer Articles';
        $Sender->Pager->ClientID = 'Pager';
        $Sender->Pager->Configure(
            $Offset,
            $Limit,
            $CountArticles,
            UserUrl($Sender->User, '', 'articles') . '/{Page}'
        );

        // Deliver JSON data if necessary
        if ($Sender->DeliveryType() != DELIVERY_TYPE_ALL && $Offset > 0) {
            $Sender->SetJson('LessRow', $Sender->Pager->ToString('less'));
            $Sender->SetJson('MoreRow', $Sender->Pager->ToString('more'));
            $Sender->View = 'articles';
        }

        // Set the HandlerType back to normal on the profilecontroller so that it fetches it's own views
        $Sender->HandlerType = HANDLER_TYPE_NORMAL;

        // Do not show article options
        $Sender->ShowOptions = false;

        if ($Sender->Head) {
            // These pages offer only duplicate content to search engines and are a bit slow.
            $Sender->Head->AddTag('meta', array('name' => 'robots', 'content' => 'noindex,noarchive'));
        }

        // Render the ProfileController
        $Sender->Render();
    }
}
