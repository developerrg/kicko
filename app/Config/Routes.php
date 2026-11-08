<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
/* GET METHODS */
$routes->get('/', 'Home::index');
// google sign
$routes->match(['get', 'post'], 'GoogleLoginCallback/googleLogin', 'GoogleLoginCallback::googleLogin');

 
// login register logout
$routes->get('weblogin', 'Weblogin::index');
$routes->get('webreg', 'Weblogin::webReg');
$routes->get('webforgot', 'Weblogin::webForgot');
//$routes->post('webForgotEmailSend', 'Weblogin::webForgotEmailSend');
$routes->post('weblogin/webForgotEmailSend', 'Weblogin::webForgotEmailSend');
$routes->post('customerauth', 'Weblogin::customerAuthen');
$routes->get('logout', 'Weblogin::logout');

$routes->set404Override('\App\Controllers\ErrorWeb::show404');
$routes->get('forgotPassword/check/(:segment)', 'ForgotPassword::checkingToken/$1');
$routes->post('resetPassword', 'ForgotPassword::resetPassword');

// testing

$routes->get('OrderNow', 'OrderNow::index');
$routes->get('OrderNow/index', 'OrderNow::index');
$routes->post('OrderNow/saveNewAddress', 'OrderNow::saveNewAddress');
$routes->post('OrderNow/submitfrm', 'OrderNow::submitfrm');

// ordernow user

$routes->get('ordernow/getAddress/(:num)', 'OrderNow::getAddress/$1');

// products user
$routes->get('product', 'Product::index');
$routes->get('product/details', 'ProductDetails::index');
$routes->get('product/product_details/(:num)', 'Product::product_details/$1');
$routes->get('product/loadMore', 'Product::loadMore');
$routes->get('/similar-products/(:num)', 'Product::similarProducts/$1');
$routes->get('product/search', 'Product::ajaxSearch');
$routes->get('product/product_list', 'Product::products_lists');
$routes->get('product/product_list/category/(:num)', 'Product::product_list_by_category/$1');
$routes->get('product/product_list/subcategory/(:num)', 'Product::product_list_by_subcategory/$1');
$routes->post('product/submit', 'Product::submit');
$routes->get('product/viewcollection', 'Product::view_collection');
$routes->get('review/loadRandomSimilar/(:num)', 'Review::loadRandomSimilar/$1');
$routes->get('product/loadMoreSearch', 'Product::loadMoreSearch');
$routes->get('product/load-more-reviews/(:num)', 'Product::loadMoreReviews/$1');
$routes->get('subcategory/subcategoryProducts/(:num)/(:num)', 'Subcategory::subcategoryProducts/$1/$2');
$routes->get('subcategory/loadMoreSubcategoryProducts/(:num)/(:num)', 'Subcategory::loadMoreSubcategoryProducts/$1/$2');

$routes->match(['get', 'post'], 'weblogin/create', 'Weblogin::createnew');
$routes->get('product/loadMoreByDate', 'Product::loadMoreByDate');
$routes->get('category/loadMoreSearch', 'Category::loadMoreSearch');
$routes->post('category/loadMoreByDate/(:num)', 'Category::loadMoreByDate/$1');
$routes->get('contact', 'Contact::index');
$routes->post('contact/submit', 'Contact::submit');
$routes->post('review/submit', 'Review::submit');
$routes->get('review/(:num)/(:num)', 'Review::loaddetails/$1/$2');
$routes->get('profile', 'Profile::index');
$routes->post('profile/update', 'Profile::update');
$routes->post('profile/address/add', 'Profile::addAddress');
$routes->post('profile/address/edit', 'Profile::editAddress');
//$routes->post('profile/address/delete', 'Profile::deleteAddress');
$routes->post('profile/deleteAddress', 'Profile::deleteAddress');
$routes->post('/profile/setDefaultAddress', 'Profile::setDefaultAddress');
$routes->post('profile/update', 'Profile::update');
$routes->post('profile/getAddress', 'Profile::getAddress');
$routes->post('profile/editprofile', 'Profile::editProfile');
// $routes->match(['get', 'post'], 'profile/editprofile', 'Profile::add');
$routes->match(['get', 'post'], 'profile/change_password', 'Profile::changePassword');

$routes->get('product/details', 'ProductDetails::index');
$routes->get('product', 'Product::index');
$routes->get('product/product_details/(:num)', 'Product::product_details/$1');
$routes->get('product/search', 'Product::ajaxSearch');
$routes->get('product/products_lists', 'Product::products_lists');
$routes->get('product/product_list', 'Product::products_lists');
$routes->get('ordernow', 'OrderNow::index');
$routes->get('ordernow/product/(:any)', 'OrderNow::orderproduct/$1');
$routes->post('ordernow/submitfrm', 'OrderNow::submitfrm');

$routes->get('category/category_list', 'Category::category_list');
$routes->get('category/catProducts/(:num)', 'Category::catProducts/$1');
$routes->get('subcategory/subcategoryProducts/(:num)/(:num)', 'Subcategory::subcategoryProducts/$1/$2');
//About Us
$routes->get('aboutus', 'AboutUs::index');

//category
$routes->get('delivery', 'Delivery::index');
$routes->get('Privacypolicy', 'Privacypolicy::index');
$routes->get('Termsandconditions', 'Termsandconditions::index');
$routes->get('Return_refundpolicy', 'ReturnAndRefundPolicy::index');

// --------------------------------ADMIN----------------------------------------//


// $routes->group('admin', ['namespace' => 'App\Controllers\admin'], function($routes) {
$routes->get('admin', 'Admin\Home::index');
$routes->post('admin/Auth', 'Admin\Auth::authenticate');
$routes->get('admin/dashboard', 'Admin\Dashboard::index');


//category
$routes->get('admin/category', 'Admin\Category::index');
$routes->post('admin/category/List', 'Admin\Category::ajaxList');
$routes->get('admin/category/add', 'Admin\Category::addCategory');
$routes->get('admin/category/edit/(:num)', 'Admin\Category::addCategory/$1');
$routes->post('admin/category/save', 'Admin\Category::saveCategory');
$routes->post('admin/category/status', 'Admin\Category::changeStatus');
$routes->post('admin/category/delete/(:any)', 'Admin\Category::deleteCategory/$1');

//Accessory
$routes->get('admin/accessory', 'Admin\Accessory::index');
$routes->post('admin/accessory/List', 'Admin\Accessory::ajaxList');
$routes->get('admin/accessory/add', 'Admin\Accessory::addAccessory');
$routes->get('admin/accessory/edit/(:num)', 'Admin\Accessory::addAccessory/$1');
$routes->post('admin/accessory/save', 'Admin\Accessory::saveAccessory');
$routes->post('admin/accessory/status', 'Admin\Accessory::changeStatus');
$routes->post('admin/accessory/delete/(:any)', 'Admin\Accessory::deleteAccessory/$1');

//Subcategory
$routes->get('admin/subcategory', 'Admin\Subcategory::index');
$routes->post('admin/subcategory/List', 'Admin\Subcategory::ajaxList');
$routes->get('admin/subcategory/add', 'Admin\Subcategory::addSubcategory');
$routes->get('admin/subcategory/edit/(:num)', 'Admin\Subcategory::addSubcategory/$1');
$routes->post('admin/subcategory/save', 'Admin\Subcategory::saveSubcategory');
$routes->post('admin/subcategory/delete/(:any)', 'Admin\Subcategory::deleteSubcategory/$1');
$routes->post('admin/subcategory/status', 'Admin\Subcategory::changeStatus');

//admin Products
$routes->get('admin/product', 'Admin\Product::index');
$routes->post('admin/product/List', 'Admin\Product::ajaxList');
$routes->get('admin/product/add', 'Admin\Product::addProduct');
$routes->get('admin/product/edit/(:num)', 'Admin\Product::addProduct/$1');
$routes->post('admin/product/save', 'Admin\Product::saveProduct');
$routes->post('admin/product/delete/(:any)', 'Admin\Product::deleteProduct/$1');
$routes->post('admin/product/get-subcategories', 'Admin\Product::getSubcategories');
$routes->post('admin/product/upload-media', 'Admin\Product::uploadMedia');
$routes->get('admin/product/get-product-images/(:num)', 'Admin\Product::getProductImages/$1');
$routes->post('admin/product/delete-product-image', 'Admin\Product::deleteProductImage');
$routes->post('admin/product/video', 'Admin\Product::ProductuploadVideo');
$routes->post('admin/product/getVideo', 'Admin\Product::getVideo');
$routes->post('admin/product/deletevideo', 'Admin\Product::deleteVideo');
$routes->post('admin/product/status', 'Admin\Product::changeStatus');
$routes->get('admin/product/view/(:any)', 'Admin\Product::viewProduct/$1');
$routes->get('admin/update_stock/(:num)', 'Admin\Stock::updateStockForm/$1');
$routes->post('admin/update_stock_value/(:num)', 'Admin\Stock::updateStock/$1');
$routes->get('product/reviews_view/(:num)', 'Product::reviewsView/$1');

//Staff
$routes->get('admin/staff', 'Admin\Staff::index');
$routes->post('admin/staff/List', 'Admin\Staff::ajaxList');
$routes->get('admin/staff/add', 'Admin\Staff::addStaff'); // Create
$routes->get('admin/staff/add/(:num)', 'Admin\Staff::addStaff/$1'); // Edit
$routes->post('admin/staff/status', 'Admin\Staff::updateStatus');// Update status of a staff
$routes->post('admin/staff/save', 'Admin\Staff::createnew');
$routes->post('admin/staff/delete/(:any)', 'Admin\Staff::deleteStaff/$1');

//Customers
$routes->get('admin/customer', 'Admin\Customer::index');
$routes->post('admin/customer/List', 'Admin\Customer::ajaxList');
$routes->get('admin/customer/view', 'Admin\Customer::view_cust'); // Create
$routes->get('admin/customer/customer_address', 'Admin\Customer::customer_address'); // Create
$routes->get('admin/customer/view/(:num)', 'Admin\Customer::view_cust/$1'); // Edit Page
$routes->post('admin/customer/save', 'Admin\Customer::createnew');
$routes->post('admin/customer/delete/(:any)', 'Admin\Customer::deleteCust/$1');

//$routes->post('customer/updateStatus', 'Customer::updateStatus');
$routes->post('admin/customer/status', 'Admin\Customer::updateStatus');
$routes->get('admin/customer/location/(:num)', 'Admin\Customer_address::location/$1');//customer address edit
$routes->get('admin/customer_address/view/(:num)', 'Admin\Customer_address::view_address/$1');
$routes->get('admin/customer_address/view/(:num)/(:num)', 'Admin\Customer_address::view_address/$1/$2');
$routes->post('admin/customer_address/save', 'Admin\Customer_address::createnew');
$routes->post('admin/customer_address/delete/(:any)', 'Admin\Customer_address::deleteAddress/$1');

//Themes
$routes->get('admin/themes', 'Admin\Themes::index');
$routes->post('admin/themes/List', 'Admin\Themes::ajaxList');
$routes->post('admin/themes/status', 'Admin\Themes::updateStatus');
$routes->get('admin/themes/add', 'Admin\Themes::addbanner'); // Create
$routes->get('admin/themes/add/(:num)', 'Admin\Themes::addbanner/$1'); // Edit
//$routes->post('themes/save', 'Themes::save_file');
$routes->post('admin/themes/delete/(:any)', 'Admin\Themes::deleteBanner/$1');
$routes->post('admin/themes/save_file', 'Admin\Themes::save_file');
$routes->get('admin/get/themes', 'Admin\Themes::fetch_theme');

//estimates
$routes->get('admin/estimates',                'Admin\Estimates::index');
$routes->post('admin/estimates/list',          'Admin\Estimates::ajaxList');
$routes->get('admin/estimates/view/(:num)',    'Admin\Estimates::view/$1');
$routes->post('admin/estimates/markPaid',      'Admin\Estimates::markPaid');

//orders
$routes->get('admin/orders', 'Admin\Orders::index');
$routes->post('admin/orders/List', 'Admin\Orders::ajaxList');
$routes->get('admin/orders/view/(:num)', 'Admin\Orders::orderView/$1');
$routes->post('admin/orders/loadStatus/(:num)', 'Admin\Orders::orderStatusUpdation/$1');

//profile
$routes->get('admin/', 'Admin\Profile::index');
$routes->get('admin/profile', 'Admin\Profile::edit_admin');
$routes->post('admin/profile/update', 'Admin\Profile::update');
$routes->match(['GET', 'POST'], 'admin/profile/change_password', 'Admin\Profile::change_password');
$routes->post('admin/profile/list', 'Admin\Profile::ajaxList');

//logout
$routes->post('admin/logout', 'Admin\Auth::logout');

//banners
$routes->get('admin/banner', 'Admin\Banner::index');
$routes->post('admin/banner/List', 'Admin\Banner::ajaxList');
$routes->post('admin/banner/status', 'Admin\Banner::updateStatus');
$routes->get('admin/banner/add', 'Admin\Banner::addbanner'); // Create
$routes->get('admin/banner/add/(:num)', 'Admin\Banner::addbanner/$1'); // Edit
$routes->post('admin/banner/save', 'Admin\Banner::createnew');
$routes->post('admin/banner/delete/(:any)', 'Admin\Banner::deleteBanner/$1');

//admin_updation
$routes->get('/admin', 'Admin::index');
$routes->post('admin/save', 'Admin::createnew');

//App API
$routes->post('api/applogin','Api\Auth::appauth');
$routes->post('api/createaccount','Api\Auth::registerUser');


$routes->post('api/generatePDF',    'Api\PDFController::generate');
$routes->get('view360/(:num)',      'View360Controller::index/$1');

// Products API
$routes->post('api/products',         'Api\ProductController::index');
$routes->post('api/products/details', 'Api\ProductController::show');

// Estimates API
$routes->post('api/estimates',         'Api\OrderController::index');
$routes->post('api/estimates/details', 'Api\OrderController::show');