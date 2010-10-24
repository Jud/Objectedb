# Objectedb &mdash; The Mysql Object Framework

*Warning: This framework is alpha at best. I quickly created it as a proof of concept to see if this kind of database design was even practical. As such, some features are incomplete or missing; Documentation is scarce, and the code is like Walmart Brand spaghetti.*

With that out of the way, I would also like to ask for help from the community. If you see a better way of doing something - fork the repo and submit the changes back into the main line.

## What does Objectedb Solve?
Suppose you are creating a shopping cart platform. Each user will add products and then give them a name, description, and price. Once you launch the platform, users start asking you for the ability to add other fields, such as color, material, etc. You could tell them to put it in the "description" field, or you could make a one-to-many type layout with "tags", but how would you designate if those tags were public, private, or searchable? You could add more columns, but this is starting to look like it won't scale very well.

Objectedb cures all of this by simplifying the DB down into distinct "Objects", each with their own field lists and indexes. 

## Show me some code, I still don't get it

Creating an Object (a Product object in this case)
    <?php
        $product = new Product();
            $product->name	= 'Juds Laptop';
            $product->price	= 599.00;
            $product->description = 'Really long description here.';
            $product->color	= 'Red';
            $product->model	= '1';
        $product->save();
    ?>

How to look up an Object by an index.
    <?php
        $product = Product::findByModel(1);
    ?>

