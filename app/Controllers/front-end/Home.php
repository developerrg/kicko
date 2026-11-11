<?php

namespace App\Controllers;

use App\Models\ProductDisplayModel;
use App\Models\Admin\Theme_Model;
use App\Models\ReviewModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class Home extends BaseController
{
    protected $productdisplayModel;
    protected $categories;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->productdisplayModel = new ProductDisplayModel();
        $this->categories = $this->productdisplayModel->getAllCategoriesAndSub();
    }

    public function index()
    {
        $data['categories'] = $this->categories;
        $data['title'] = 'Homepage';

        $products = $this->productdisplayModel->getAllProducts();
        $reviewModel = new \App\Models\ReviewModel();
        $productIds = array_column($products, 'pr_Id');
        $avgRatings = $reviewModel->getAverageRatingForProducts($productIds);
        $ratingsMap = [];
        foreach ($avgRatings as $rating) {
            $ratingsMap[$rating['pr_Id']] = $rating['avg_rating'];
        }
        foreach ($products as &$product) {
            $product['avg_rating'] = $ratingsMap[$product['pr_Id']] ?? 0;
        }
        $data['product'] = $products;
        // Load theme
        $themeModel = new \App\Models\Admin\Theme_Model();
        $themes = $themeModel->fetchTheme();
        if (!empty($themes)) {
            $data['themes'] = $themes[0];
        }
        // Load views
        $template = view('common/header', $data);
        $template .= view('banner');
        $template .= view('category', $data);
        $template .= view('top_products', $data);
        $template .= view('footer_banner', $data);
        $template .= view('common/footer', $data);

        return $template;
    }

}
