<?php
/*
  $Id: wishlist.php $
  TomatoCart Open Source Shopping Cart Solutions
  http://www.tomatocart.com

  Copyright (c) 2009 Wuxi Elootec Technology Co., Ltd

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License v2 (1991)
  as published by the Free Software Foundation.
*/

	class toC_Wishlist {
	  var $_contents = array(),
	      $_wishlists_id = null,
	      $_token = null;
	      
	  function toC_Wishlist() {
	    if (!isset($_SESSION['toC_Wishlist_data'])) {
	      $_SESSION['toC_Wishlist_data'] = array('contents' => array(), 
	                                             'wishlists_id' => null, 
	                                             'token' => null);
	    }
	    
	    $this->_contents =& $_SESSION['toC_Wishlist_data']['contents'];
	    $this->_wishlists_id =& $_SESSION['toC_Wishlist_data']['wishlists_id'];
	    $this->_token =& $_SESSION['toC_Wishlist_data']['token'];
	  }
	  
	  function exists($products_id) {
	    return isset($this->_contents[$products_id]);	  
	  }
	  
	  function hasContents() {
	    return !empty($this->_contents);
	  }

	  function hasWishlistID() {
      return !empty($this->_wishlists_id);
    }
    
    function getToken() {
      return $this->_token;
    }
    	  
	  function reset() {
      $this->_wishlists_id = null;
      $this->_token = null;
	    $this->_contents = array();
    }
    
    function generateToken() {
      global $osC_Customer;
      
      $token = md5($osC_Customer->getID() . time());
      
      return $token;
    }
    
    /**
     * Snychorize the wishlist data as the customer logged in
     * 
     * @access public
     * @return boolean
     */
	  function synchronizeWithDatabase() {
      global $osC_Database, $osC_Services, $osC_Language, $osC_Customer, $osC_Image;

      if (!$osC_Customer->isLoggedOn()) {
        return false;
      }

      $Qcheck = $osC_Database->query('select wishlists_id, wishlists_token from :table_wishlists where customers_id = :customers_id');
      $Qcheck->bindTable(':table_wishlists', TABLE_WISHLISTS);
      $Qcheck->bindInt(':customers_id', $osC_Customer->getID());
      $Qcheck->execute();
      
	    if ($Qcheck->numberOfRows() > 0) {
        $this->_wishlists_id = $Qcheck->valueInt('wishlists_id');
        $this->_token = $Qcheck->value('wishlists_token');
        
  	    // reset per-session cart contents, but not the database contents
        $this->_contents = array();
  
        $Qproducts = $osC_Database->query('select wishlists_products_id, products_id, date_added, comments from :table_wishlist_products where wishlists_id = :wishlists_id');
        $Qproducts->bindTable(':table_wishlist_products', TABLE_WISHLISTS_PRODUCTS);
        $Qproducts->bindInt(':wishlists_id', $this->_wishlists_id);
        $Qproducts->execute();      
        
        while ($Qproducts->next()) {
        	$product_id_string = $Qproducts->value('products_id');
        	
          $osC_Product = new osC_Product($Qproducts->value('products_id'));
          
          $product_price = $osC_Product->getPrice();
          $product_name = $osC_Product->getTitle();
          $product_image = $osC_Product->getImage();
  
          if ($osC_Services->isStarted('specials')) {
            global $osC_Specials;
  
            if ($new_price = $osC_Specials->getPrice(osc_get_product_id($Qproducts->value('products_id')))) {
              $price = $new_price;
            }
          }
          
          //process the variants products in the wishlist
          if ($osC_Product->hasVariants()) {
            $Qvariants = $osC_Database->query('select products_variants_groups_id, products_variants_groups, products_variants_values_id, products_variants_values from :table_wishlists_products_variants where lists_id = :whishlists_id and lists_products_id = :whishlists_products_id');
            $Qvariants->bindTable(':table_wishlists_products_variants', TABLE_WISHLISTS_PRODUCTS_VARIANTS);
            $Qvariants->bindInt(':whishlists_id', $this->_wishlists_id);
            $Qvariants->bindInt(':whishlists_products_id', $Qproducts->valueInt('wishlists_products_id'));
            $Qvariants->execute();
            
            if ($Qvariants->numberOfRows() > 0) {
              while($Qvariants->next()) {
                $row_variants = array(
									'groups_id' => $Qvariants->valueInt('products_variants_groups_id'), 
									'values_id' => $Qvariants->valueInt('products_variants_values_id'), 
									'groups_name' => $Qvariants->value('products_variants_groups'), 
									'values_name' => $Qvariants->value('products_variants_values')
                );
                
                //synchronize the variants products
                if (!osc_empty($row_variants)) {
                	$product_name .= '<br />';
	                
                	foreach($row_variants as $variant) {
                		$variants = array();
                		 
                		$variants[$variant['groups_id']] = $variant['values_id'];
                		$product_name .= '<em>' . $variant['groups_name'] . ': ' . $variant['values_name'] . '</em>' . '<br />';
                	}
                
                	if (is_array($variants) && !osc_empty($variants)) {
                		$product_id_string = osc_get_product_id_string($Qproducts->value('products_id'), $variants);
                
                		// update product price and image according to the variants
                		$product_price = $osC_Product->getPrice ( $variants );
                		$product_image = $osC_Product->getImage ( $variants );
                	}
                }
                
                $this->_contents[$product_id_string] = array(
                		'product_id_string' => $product_id_string,
                		'name' => $product_name,
                		'image' => $product_image,
                		'price' => $product_price,
                		'date_added' => osC_DateTime::getShort($Qproducts->value('date_added')),
                		'comments' => $Qproducts->value('comments')
                );
              }
              
              $Qvariants->freeResult();
            }
          }else {
          	$this->_contents[$product_id_string] = array(
          			'product_id_string' => $product_id_string,
          			'name' => $product_name,
          			'image' => $product_image,
          			'price' => $product_price,
          			'date_added' => osC_DateTime::getShort($Qproducts->value('date_added')),
          			'comments' => $Qproducts->value('comments')
          	);
          }
        }
        
        $Qproducts->freeResult();
        
      } else {
        $token = $this->generateToken();
        
        $Qupdate = $osC_Database->query('update :table_wishlists set customers_id = :customers_id, wishlists_token = :wishlists_token where wishlists_id = :wishlists_id');
        $Qupdate->bindTable(':table_wishlists', TABLE_WISHLISTS);
        $Qupdate->bindInt(':customers_id', $osC_Customer->getID());
        $Qupdate->bindValue(':wishlists_token', $token);
        $Qupdate->bindInt(':wishlists_id', $this->_wishlists_id);
        $Qupdate->execute();
        
        $this->_token = $token;
      }
    }
    
    /**
     * Add a product or variant product into the wishlist
     * 
     * @access public
     * @param mixed products id or products id string including the variants such as 1#1:1;2:3
     * @return boolean
     */
    function add($products_id_string) {
			global $osC_Database, $osC_Services, $osC_Customer, $osC_Product;
			
			//flag to reprent the action performed or not
			$error = false;
			
			// if wishlist empty, create a new wishlist
			if (! $this->hasWishlistID ()) {
				$token = $this->generateToken ();
				
				$Qnew = $osC_Database->query ( 'insert into :table_wishlists (customers_id, wishlists_token) values (:customers_id, :wishlists_token)' );
				$Qnew->bindTable ( ':table_wishlists', TABLE_WISHLISTS );
				$Qnew->bindInt ( ':customers_id', $osC_Customer->getID () );
				$Qnew->bindValue ( ':wishlists_token', $token );
				$Qnew->execute ();
				
				$this->_wishlists_id = $osC_Database->nextID ();
				$this->_token = $token;
				
				$Qnew->freeResult ();
			}
			
			if (! isset ( $osC_Product )) {
				$osC_Product = new osC_Product ( $products_id_string );
			}
			
			if ($osC_Product->getID () > 0) {
				if (! $this->exists ( $products_id_string )) {
					$product_price = $osC_Product->getPrice ();
					$product_name = $osC_Product->getTitle ();
					$product_image = $osC_Product->getImage ();
					
					if ($osC_Services->isStarted ( 'specials' )) {
						global $osC_Specials;
						
						if ($new_price = $osC_Specials->getPrice ( $products_id )) {
							$price = $new_price;
						}
					}
					
					// if the product has variants, set the image, price etc according to the variants
					if ($osC_Product->hasVariants ()) {
						$variants_groups = $osC_Product->getData ( 'variants_groups' );
						$variants_groups_values = $osC_Product->getData ( 'variants_groups_values' );
						
						if (preg_match('/^[0-9]+(#?([0-9]+:?[0-9]+)+(;?([0-9]+:?[0-9]+)+)*)*$/', $products_id_string)) {
						  $variants = osc_parse_variants_from_id_string($products_id_string);
						}
						
						$products_variants = $osC_Product->getVariants ();
						
						if (is_array ( $variants ) && ! osc_empty ( $variants )) {
							$products_variant = $products_variants [$products_id_string];
						} else {
							$products_variant = $osC_Product->getDefaultVariant ();
							
							$default_variants_string = $products_variant ['product_id_string'];
							$variants = osc_parse_variants_from_id_string ( $default_variants_string );
						}
						
						$variants_groups_id = $products_variant ['groups_id'];
						$variants_groups_name = $products_variant ['groups_name'];
						
						if (! osc_empty ( $variants_groups_name )) {
							$product_name .= '<br />';
							foreach ( $variants_groups_name as $group_name => $value_name ) {
								$product_name .= '<em>' . $group_name . ': ' . $value_name . '</em>' . '<br />';
							}
						}
						
						// update product price and image according to the variants
						$product_price = $osC_Product->getPrice ( $variants );
						$product_image = $osC_Product->getImage ( $variants );
					}
					
					$this->_contents [$products_id_string] = array (
							'products_id_string' => $products_id_string,
							'name' => $product_name,
							'image' => $product_image,
							'price' => $product_price,
							'date_added' => osC_DateTime::getShort ( osC_DateTime::getNow () ),
							'comments' => '' 
					);
					
					// insert into wishlist products only if there isn't the same product existing in the table
					$QnewCheck = $osC_Database->query('select * from :table_wishlist_products where products_id_string = :products_id_string limit 1');
					$QnewCheck->bindTable(':table_wishlist_products', TABLE_WISHLISTS_PRODUCTS);
					$QnewCheck->bindValue(':products_id_string', $products_id_string);
					$QnewCheck->execute();
					
					if ($QnewCheck->numberOfRows() < 1) {
						$Qnew = $osC_Database->query ( 'insert into :table_wishlist_products (wishlists_id, products_id_string, date_added, comments) values (:wishlists_id, :products_id_string, now(), :comments)' );
						$Qnew->bindTable ( ':table_wishlist_products', TABLE_WISHLISTS_PRODUCTS );
						$Qnew->bindInt ( ':wishlists_id', $this->_wishlists_id );
						$Qnew->bindValue(':products_id_string', $products_id_string);
						$Qnew->bindValue ( ':comments', '' );
						$Qnew->execute ();
							
						$wishlists_products_id = $osC_Database->nextID ();
					}else {
						$wishlists_products_id = $QnewCheck->valueInt('wishlists_products_id');
					}
					
					$QnewCheck->freeResult();
				}else {
			  	$error = true;
				}
			}
			
			if ($error === true) {
			  return false;
			}
			
			return true;
		}
    
    function getProducts() {
      global $osC_Customer;
      
      $products = array();
      
      if ($this->hasContents()) {
        foreach ($this->_contents as $products_id_string => $data) {
          $products[] = $data;
        }

        return $products;        
      }
           
      return false;      
    }    
	  
    function updateWishlist($comments) {
      global $osC_Database, $osC_Customer;
      
      $error = false;
      
      foreach($comments as $products_id => $comment) {
        $this->_contents[$products_id]['comments'] = $comment;
        
        $Qupdate = $osC_Database->query('update :table_wishlist_products set comments = :comments where wishlists_id = :wishlists_id and products_id = :products_id');
        $Qupdate->bindTable(':table_wishlist_products', TABLE_WISHLISTS_PRODUCTS);
        $Qupdate->bindValue(':comments', $comment);
        $Qupdate->bindInt(':wishlists_id', $this->_wishlists_id);
        $Qupdate->bindInt(':products_id', $products_id);
        $Qupdate->execute();
        
        if ($osC_Database->isError()) {       
          $error = true;
          break;      
        }
      }
      
      if ($error === false) {
        return true;
      }
      
      return false;
    }
    
    function hasProduct($products_id) {
      if (isset($this->_contents[$products_id])) {
        return true;
      }
      
      return false;
    }
    
    /**
     * Delete the wishlist product
     * 
     * @access public
     * @param mixed product id or product id string with the correlative variants info
     * @return mixed
     */
    function deleteProduct($products_id_string) {
      global $osC_Customer, $osC_Database;
      
      //parse products id and variants from product id string
      if (preg_match('/^[0-9]+(_?([0-9]+:?[0-9]+)+(;?([0-9]+:?[0-9]+)+)*)*$/', $products_id_string)) {
      	$products_id_string = str_replace('_', '#', $products_id_string);
        $products_id = osc_get_product_id($products_id_string);
        $variants = osc_parse_variants_from_id_string($products_id_string);
      }else {
      	$products_id = $products_id_string;
      }
      
      $Qcheck = $osC_Database->query('select wishlists_products_id from :table_wishlist_products where products_id = :products_id and wishlists_id = :wishlists_id');
      $Qcheck->bindTable(':table_wishlist_products', TABLE_WISHLISTS_PRODUCTS);
      $Qcheck->bindInt(':products_id', $products_id);
      $Qcheck->bindInt(':wishlists_id', $this->_wishlists_id);
      $Qcheck->execute();
      
      if ($Qcheck->numberOfRows() > 0) {
      	$row = $Qcheck->toArray();
      
      	$wishlists_products_id = $row['wishlists_products_id'];
      }
      $Qcheck->freeResult();
      
      //process the variants products in the wishlists
      if (isset($variants)) {
        foreach ($variants as $groups_id => $values_id) {
        	if (isset($wishlists_products_id)) {
        			$QdeleteVariants = $osC_Database->query('delete from :table_wishlists_products_variants where lists_id = :wishlists_id and lists_products_id = :wishlists_products_id and products_variants_groups_id = :products_variants_groups_id and products_variants_values_id = :products_variants_values_id');
        			$QdeleteVariants->bindTable(':table_wishlists_products_variants', TABLE_WISHLISTS_PRODUCTS_VARIANTS);
        			$QdeleteVariants->bindInt(':wishlists_id', $this->_wishlists_id);
        			$QdeleteVariants->bindInt(':wishlists_products_id', $wishlists_products_id);
        			$QdeleteVariants->bindInt(':products_variants_groups_id', $groups_id);
        			$QdeleteVariants->bindInt(':products_variants_values_id', $values_id);
        			$QdeleteVariants->execute();
					}
        }
      }
      
      //delete the products only when there isn't any record in the variants wishlists table
      $Qvariants_check = $osC_Database->query('select wishlists_products_variants_id from :table_wishlists_products_variants where lists_id = :wishlists_id and lists_products_id = :wishlists_products_id limit 1');
      $Qvariants_check->bindTable(':table_wishlists_products_variants', TABLE_WISHLISTS_PRODUCTS_VARIANTS);
      $Qvariants_check->bindInt(':wishlists_id', $this->_wishlists_id);
			$Qvariants_check->bindInt(':wishlists_products_id', $wishlists_products_id);
			$Qvariants_check->execute();
			
			if ($Qvariants_check->numberOfRows() < 1) {
				$Qdelete = $osC_Database->query('delete from :table_wishlist_products where products_id = :products_id and wishlists_id = :wishlists_id');
				$Qdelete->bindTable(':table_wishlist_products', TABLE_WISHLISTS_PRODUCTS);
				$Qdelete->bindInt(':products_id', $products_id);
				$Qdelete->bindInt(':wishlists_id', $this->_wishlists_id);
				$Qdelete->execute();
			}
			
			$Qvariants_check->freeResult();
      
      if (!$osC_Database->isError()) {
        if (isset($this->_contents[$products_id_string])) {
          unset($this->_contents[$products_id_string]);
        }
        
        //when the products is empty, delete wishlist
        if ((!$this->hasContents())) {
          $Qdelete = $osC_Database->query('delete from :table_wishlist where wishlists_id = :wishlists_id');
          $Qdelete->bindTable(':table_wishlist', TABLE_WISHLISTS);
          $Qdelete->bindInt(':wishlists_id', $this->_wishlists_id);
          $Qdelete->execute();
          
          if ($osC_Database->isError()) {
            return false;
          }
          
          $this->_wishlists_id = null;
          $this->_token = null;
        }
        
        return true;
      }
      
      return false;
    }
  }
?>