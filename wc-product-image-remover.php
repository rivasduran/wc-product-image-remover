<?php
/**
 * Plugin Name: WooCommerce Product Image Remover (Complete Removal with Variation Galleries)
 * Description: Allows removing all images from products in bulk, including main images, galleries, thumbnails, variation images, and custom variation galleries.
 * Version: 1.0.3
 * Author: RivasDuran
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Product_Image_Remover {
    public function __construct() {
        add_action('admin_init', array($this, 'add_bulk_actions'));
        add_action('admin_action_remove_product_images', array($this, 'process_bulk_action'));
    }

    public function add_bulk_actions() {
        add_filter('bulk_actions-edit-product', array($this, 'register_bulk_action'));
    }

    public function register_bulk_action($bulk_actions) {
        $bulk_actions['remove_product_images'] = __('Remove all images', 'woocommerce');
        return $bulk_actions;
    }

    public function process_bulk_action() {
        if (!isset($_REQUEST['post']) || !is_array($_REQUEST['post'])) {
            return;
        }

        $product_ids = array_map('intval', $_REQUEST['post']);

        foreach ($product_ids as $product_id) {
            $this->remove_images($product_id);
        }

        $redirect_url = add_query_arg('removed_images', count($product_ids), wp_get_referer());
        wp_safe_redirect($redirect_url);
        exit;
    }

    private function remove_images($product_id) {
        $product = wc_get_product($product_id);

        if (!$product) {
            return;
        }

        // Remove main product image
        $this->delete_image($product->get_image_id());
        $product->set_image_id(0);

        // Remove product gallery
        $gallery_image_ids = $product->get_gallery_image_ids();
        foreach ($gallery_image_ids as $image_id) {
            $this->delete_image($image_id);
        }
        $product->set_gallery_image_ids(array());

        // If it's a variable product, remove variation images and galleries
        if ($product->is_type('variable')) {
            $variations = $product->get_children();
            foreach ($variations as $variation_id) {
                $this->remove_variation_images($variation_id);
            }
        }

        // Remove WooCommerce Additional Variation Images Gallery
        delete_post_meta($product_id, 'woo_variation_gallery_images');

        // Remove Woodmart Variation Gallery
        delete_post_meta($product_id, 'woodmart_variation_gallery_data');

        $product->save();
    }

    private function remove_variation_images($variation_id) {
        $variation = wc_get_product($variation_id);
        if ($variation) {
            // Remove main variation image
            $this->delete_image($variation->get_image_id());
            $variation->set_image_id(0);

            // Remove WooCommerce Additional Variation Images Gallery
            $woo_variation_gallery = get_post_meta($variation_id, 'woo_variation_gallery_images', true);
            if (is_array($woo_variation_gallery)) {
                foreach ($woo_variation_gallery as $image_id) {
                    $this->delete_image($image_id);
                }
            }
            delete_post_meta($variation_id, 'woo_variation_gallery_images');

            // Remove Woodmart Variation Gallery
            $woodmart_variation_gallery = get_post_meta($variation_id, 'woodmart_variation_gallery_data', true);
            if (is_array($woodmart_variation_gallery)) {
                foreach ($woodmart_variation_gallery as $image_id) {
                    $this->delete_image($image_id);
                }
            }
            delete_post_meta($variation_id, 'woodmart_variation_gallery_data');

            $variation->save();
        }
    }

    private function delete_image($image_id) {
        if ($image_id) {
            wp_delete_attachment($image_id, true);
        }
    }
}

new WC_Product_Image_Remover();