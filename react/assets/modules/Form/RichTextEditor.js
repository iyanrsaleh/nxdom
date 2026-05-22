import React, { forwardRef, useImperativeHandle, useRef, useState } from 'react';
import { View, Text, Button, Dimensions } from 'react-native';
import { RichEditor, RichToolbar } from 'react-native-pell-rich-editor';
import { actions } from 'react-native-pell-rich-editor';

const RichTextEditor = forwardRef((props, ref) => {
  const richText = useRef();
  // Gunakan props.value sebagai nilai awal jika ada
  const [content, setContent] = useState(props.value || '');

  const windowHeight = Dimensions.get('window').height;
  // Mengatur tinggi editor agar tidak terlalu panjang
  const editorHeight = Math.min(windowHeight * 0.25, 150);

  useImperativeHandle(ref, () => ({
    getContentHtml: () => content,
  }));

  const handleContentChange = (newContent) => {
    setContent(newContent);
    if (props.onChangeText) {
      props.onChangeText(newContent);
    }
  };

  return (
    <View>
      <RichToolbar
        editor={richText}
        actions={[
          actions.setBold,
          actions.setItalic,
          actions.setUnderline,
          actions.alignLeft,
          actions.alignCenter,
          actions.alignRight,
          actions.insertBulletsList,
          actions.insertOrderedList,
          actions.undo,
          actions.redo,
          actions.heading1,
          actions.heading2,
          actions.heading3,
          actions.setBlockquote,
        ]}
        iconMap={{
          [actions.heading1]: ({ tintColor }) => (
            <Text style={[{ color: tintColor }]}>H1</Text>
          ),
          [actions.heading2]: ({ tintColor }) => (
            <Text style={[{ color: tintColor }]}>H2</Text>
          ),
          [actions.heading3]: ({ tintColor }) => (
            <Text style={[{ color: tintColor }]}>H3</Text>
          ),
          [actions.setTextColor]: ({ tintColor }) => (
            <Text style={[{ color: tintColor }]}>T</Text>
          ),
          [actions.setBackgroundColor]: ({ tintColor }) => (
            <Text style={[{ color: tintColor }]}>BG</Text>
          ),
          [actions.insertLink]: ({ tintColor }) => (
            <Text style={[{ color: tintColor }]}>Link</Text>
          ),
        }}
      />
      <RichEditor
        ref={richText}
        placeholder={props.placeholder || "Mulai menulis di sini..."}
        onChange={handleContentChange}
        initialContentHTML={props.value}
        initialHeight={editorHeight}
      />
    </View>
  );
});

export default RichTextEditor;
