<?php if(!defined('APPLICATION')) exit();

/**
 * Handles data for articles.
 */
class ArticleModel extends ArticlesModel {
   /**
    * Class constructor. Defines the related database table name.
    */
   public function __construct() {
      parent::__construct('Article');
   }

   /**
    * Gets the data for multiple articles based on given criteria.
    *
    * @param int $Offset Number of articles to skip.
    * @param bool $Limit Max number of articles to return.
    * @param array $Wheres SQL conditions.
    *
    * @return Gdn_DataSet SQL result.
    */
   public function Get($Offset = 0, $Limit = FALSE, $Wheres = NULL) {
      // Set up selection query.
      $this->SQL->Select('a.*')->From('Article a');

      // Assign up limits and offsets.
      if(!$Limit)
         $Limit = Gdn::Config('Articles.Articles.PerPage', 12);

      $Offset = !is_numeric($Offset) || ($Offset < 0 ? 0 : $Offset);

      if(($Offset !== FALSE) && ($Limit !== FALSE))
         $this->SQL->Limit($Limit, $Offset);

      // Handle SQL conditions for wheres.
      $this->EventArguments['Wheres'] = &$Wheres;
      $this->FireEvent('BeforeGet');

      if(is_array($Wheres))
         $this->SQL->Where($Wheres);

      // Set order of data.
      $this->SQL->OrderBy('a.DateInserted', 'desc');

      // Fetch data.
      $Articles = $this->SQL->Get();

      // Prepare and fire event.
      $this->EventArguments['Data'] = $Articles;
      $this->FireEvent('AfterGet');

      return $Articles;
   }

   public function GetByID($ArticleID) {
      // Set up the query.
      $this->SQL->Select('a.*')
         ->From('Article a')
         ->Where('a.ArticleID', $ArticleID);

      // Fetch data.
      $Article = $this->SQL->Get()->FirstRow();

      return $Article;
   }

   public function GetByUrlCode($ArticleUrlCode) {
      // Set up the query.
      $this->SQL->Select('a.*')
         ->From('Article a')
         ->Where('a.UrlCode', $ArticleUrlCode);

      // Fetch data.
      $Article = $this->SQL->Get()->FirstRow();

      return $Article;
   }
}