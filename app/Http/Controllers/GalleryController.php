<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GalleryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('galleries.index');
    }

    /**
     * Display the albums (galleries) page.
     *
     * @return \Illuminate\Http\Response
     */
    public function albums()
    {
        return view('galleries.albums');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return view('galleries.show', ['galleryId' => $id]);
    }

}
