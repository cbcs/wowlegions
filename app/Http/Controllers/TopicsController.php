<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\{Topic, Category, Reply, User};
use App\Services\ParserText;
use Cache;

class TopicsController extends Controller
{

    public function sticky_topic($id) {
        Topic::where('id', $id)->update(['sticky' => '1']);
        return back();
    }

    public function closed_topic($id) {
        Topic::where('id', $id)->update(['closed' => '1']);
        return back();
    }

    public function delete($id, $fid) {
        Topic::where('id', $id)->delete();
        return redirect()->route('forum', [$fid]);
    }

    public function edit($category, Topic $topic)
    {
        return view('forum.topics.edit', compact('category', 'topic'));
    }

    public function store(Category $category)
    {
        $this->validate(request(), [
          'subject' => 'required|max:75',
          'messages' => 'required|max:2000'
        ]);

        $bb = new ParserText();

        $topic = Topic::create([
          'title'       => request('subject'),
          'content'     => $bb->parse(request('messages')),
          'category_id' => $category->id,
          'user_id'  => \Auth::user()->id,
          'characters_id' => \Auth::user()->charactersActive,
        ]);

        return redirect()->route('forum.topic', [$category->id, $topic->id]);
    }

    public function store_reply($category, Topic $topic)
    {
        $this->validate(request(), [
            'detail' => 'required|max:2000'
        ]);

        $bb = new ParserText();

        $topic->replies()->create([
            'content'  => $bb->parse(request('detail')),
            'user_id'  => \Auth::user()->id,
            'characters_id' => \Auth::user()->charactersActive,
        ]);

        return back();
    }

    public function show($category, Topic $topic)
    {
        $replies = $topic->replies()->paginate(18);

        $categories = Cache::remember('category', "300", function () {
            return Category::whereNull('parent_id')->with('forums')->get();
        });

        return view('forum.categories.topic', compact('category', 'topic', 'replies', 'categories'));
    }


    public function search(Request $request) {

        $categories = Cache::remember('category', "300", function () {
            return Category::whereNull('parent_id')->with('forums')->get();
        });

        $q = $request->input('q');
        $max_page = 10;
        $results = $this->searchForum($q, $max_page);
        return view('forum.search', [
            'result' => $results,
            'categories' => $categories,
        ])->render();
    }

   public function searchForum($q, $count){
        $query = mb_strtolower($q, 'UTF-8');
        $arr = explode(" ", $query);
        $query = [];
        foreach ($arr as $word)
        {
            $len = mb_strlen($word, 'UTF-8');
            switch (true)
            {
                case ($len <= 3):
                {
                    $query[] = $word . "*";
                    break;
                }
                case ($len > 3 && $len <= 6):
                {
                    $query[] = mb_substr($word, 0, -1, 'UTF-8') . "*";
                    break;
                }
                case ($len > 6 && $len <= 9):
                {
                    $query[] = mb_substr($word, 0, -2, 'UTF-8') . "*";
                    break;
                }
                case ($len > 9):
                {
                    $query[] = mb_substr($word, 0, -3, 'UTF-8') . "*";
                    break;
                }
                default:
                {
                    break;
                }
            }
        }
        $query = array_unique($query, SORT_STRING);
        $qQeury = implode(" ", $query);
        $results = Topic::whereRaw("MATCH(title,content) AGAINST(? IN BOOLEAN MODE)", $qQeury)->paginate($count);
        return $results;
    }
}